<?php
declare(strict_types=1);

namespace DotShipSqlStore {
    final class ObjectId
    {
        private string $value;

        public function __construct(?string $value = null)
        {
            $value = $value !== null ? strtolower(preg_replace('/[^a-f0-9]/i', '', $value)) : '';
            $this->value = $value !== '' ? substr(str_pad($value, 24, '0'), 0, 24) : bin2hex(random_bytes(12));
        }

        public function __toString(): string
        {
            return $this->value;
        }

        public function toHexString(): string
        {
            return $this->value;
        }
    }

    final class UTCDateTime
    {
        private int $milliseconds;

        public function __construct(int|float|\DateTimeInterface|null $value = null)
        {
            if ($value instanceof \DateTimeInterface) {
                $this->milliseconds = (int) $value->format('Uv');
                return;
            }

            if (is_int($value) || is_float($value)) {
                $this->milliseconds = (int) $value;
                return;
            }

            $this->milliseconds = (int) round(microtime(true) * 1000);
        }

        public function toDateTime(): \DateTimeImmutable
        {
            $seconds = (int) floor($this->milliseconds / 1000);
            $microseconds = ($this->milliseconds % 1000) * 1000;
            $date = new \DateTimeImmutable('@' . $seconds);
            return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()))->modify('+' . $microseconds . ' microseconds');
        }

        public function getMilliseconds(): int
        {
            return $this->milliseconds;
        }
    }

    final class Regex
    {
        public string $pattern;
        public string $flags;

        public function __construct(string $pattern, string $flags = '')
        {
            $this->pattern = $pattern;
            $this->flags = $flags;
        }
    }

    final class InsertOneResult
    {
        public function __construct(private mixed $insertedId) {}
        public function getInsertedId(): mixed { return $this->insertedId; }
    }

    final class InsertManyResult
    {
        public function __construct(private array $insertedIds) {}
        public function getInsertedIds(): array { return $this->insertedIds; }
    }

    final class UpdateResult
    {
        public function __construct(private int $matchedCount, private int $modifiedCount) {}
        public function getMatchedCount(): int { return $this->matchedCount; }
        public function getModifiedCount(): int { return $this->modifiedCount; }
    }

    final class DeleteResult
    {
        public function __construct(private int $deletedCount) {}
        public function getDeletedCount(): int { return $this->deletedCount; }
    }

    final class Client
    {
        public function __construct(private string $uri) {}

        public function selectDatabase(string $name): Database
        {
            return new Database($this->uri, $name);
        }
    }

    final class Database
    {
        public function __construct(private string $uri, private string $name) {}

        public function selectCollection(string $name): Collection
        {
            return new Collection($this->uri, $this->name, $name);
        }
    }

    final class Collection
    {
        public function __construct(private string $uri, private string $database, private string $name) {}

        public function countDocuments(array $filter = []): int
        {
            return count($this->readFiltered($filter));
        }

        public function findOne(array $filter = []): ?\ArrayObject
        {
            $docs = $this->readFiltered($filter);
            $doc = $docs[0] ?? null;
            return $doc !== null ? new \ArrayObject($doc, \ArrayObject::ARRAY_AS_PROPS) : null;
        }

        public function insertOne(array $document): InsertOneResult
        {
            $document['_id'] ??= new ObjectId();
            dotship_sql_write_document($this->name, $document);
            return new InsertOneResult($document['_id']);
        }

        public function insertMany(array $documents): InsertManyResult
        {
            $insertedIds = [];

            foreach ($documents as $document) {
                $document['_id'] ??= new ObjectId();
                $insertedIds[] = $document['_id'];
                dotship_sql_write_document($this->name, $document);
            }

            return new InsertManyResult($insertedIds);
        }

        public function find(array $filter = [], array $options = []): array
        {
            $docs = $this->readFiltered($filter);

            if (!empty($options['sort']) && is_array($options['sort'])) {
                foreach (array_reverse($options['sort'], true) as $field => $direction) {
                    usort($docs, function (array $left, array $right) use ($field, $direction): int {
                        $leftValue = $left[$field] ?? null;
                        $rightValue = $right[$field] ?? null;
                        $comparison = $this->compareValues($leftValue, $rightValue);
                        return $direction < 0 ? -$comparison : $comparison;
                    });
                }
            }

            $skip = max(0, (int) ($options['skip'] ?? 0));
            $limit = array_key_exists('limit', $options) ? max(0, (int) $options['limit']) : null;
            $docs = array_slice($docs, $skip, $limit ?? null);

            return array_map(fn (array $doc) => new \ArrayObject($doc, \ArrayObject::ARRAY_AS_PROPS), $docs);
        }

        public function updateOne(array $filter, array $update): UpdateResult
        {
            $collection = dotship_sql_read_collection($this->name);

            $matched = 0;
            $modified = 0;

            foreach ($collection as $index => $document) {
                if (!dotship_sql_document_matches($document, $filter)) {
                    continue;
                }

                $matched++;
                $updated = $document;

                foreach ($update['$set'] ?? [] as $field => $value) {
                    $updated[$field] = $value;
                }

                foreach ($update['$push'] ?? [] as $field => $value) {
                    $current = $updated[$field] ?? [];
                    if (!is_array($current)) {
                        $current = [];
                    }
                    $current[] = $value;
                    $updated[$field] = $current;
                }

                foreach ($update['$inc'] ?? [] as $field => $value) {
                    $updated[$field] = (int) ($updated[$field] ?? 0) + (int) $value;
                }

                if ($updated != $document) {
                    $modified++;
                }

                dotship_sql_write_document($this->name, $updated);
                break;
            }

            return new UpdateResult($matched, $modified);
        }

        public function deleteOne(array $filter): DeleteResult
        {
            $collection = dotship_sql_read_collection($this->name);

            $deleted = 0;

            foreach ($collection as $index => $document) {
                if (!dotship_sql_document_matches($document, $filter)) {
                    continue;
                }

                $deleted = 1;
                dotship_sql_delete_document($this->name, (string) ($document['_id'] ?? ''));
                break;
            }

            return new DeleteResult($deleted);
        }

        private function readFiltered(array $filter): array
        {
            $collection = dotship_sql_read_collection($this->name);
            $matched = [];

            foreach ($collection as $document) {
                if (dotship_sql_document_matches($document, $filter)) {
                    $matched[] = $document;
                }
            }

            return $matched;
        }

        private function compareValues(mixed $left, mixed $right): int
        {
            $leftValue = $this->scalarize($left);
            $rightValue = $this->scalarize($right);

            return $leftValue <=> $rightValue;
        }

        private function scalarize(mixed $value): string
        {
            if ($value instanceof UTCDateTime) {
                return (string) $value->getMilliseconds();
            }

            if ($value instanceof ObjectId) {
                return (string) $value;
            }

            if (is_object($value) && method_exists($value, '__toString')) {
                return (string) $value;
            }

            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            return (string) $value;
        }
    }
}

namespace {
    function dotship_sql_store_path(): string
    {
        $configured = (string) dotship_env('DOTSHIP_SQLITE_PATH', '');
        if ($configured !== '') {
            return $configured;
        }

        return dirname(__DIR__) . '/storage/dotship.sqlite';
    }

    function dotship_sql_pdo(): \PDO
    {
        static $pdo = null;

        if ($pdo instanceof \PDO) {
            return $pdo;
        }

        $path = dotship_sql_store_path();
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $pdo = new \PDO('sqlite:' . $path);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE IF NOT EXISTS documents (collection TEXT NOT NULL, document_id TEXT NOT NULL, data TEXT NOT NULL, created_ms INTEGER NOT NULL, updated_ms INTEGER NOT NULL, PRIMARY KEY(collection, document_id))');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_documents_collection ON documents(collection)');

        return $pdo;
    }

    function dotship_sql_store_load(): array
    {
        $pdo = dotship_sql_pdo();
        $rows = $pdo->query('SELECT collection, data FROM documents ORDER BY rowid ASC')->fetchAll();
        $data = [];

        foreach ($rows as $row) {
            $collection = (string) ($row['collection'] ?? '');
            $decoded = json_decode((string) ($row['data'] ?? ''), true);
            if ($collection === '' || !is_array($decoded)) {
                continue;
            }

            $data[$collection][] = $decoded;
        }

        return $data;
    }

    function dotship_sql_store_save(array $data): void
    {
        $pdo = dotship_sql_pdo();
        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM documents');

            foreach ($data as $collection => $documents) {
                if (!is_array($documents)) {
                    continue;
                }

                foreach ($documents as $document) {
                    if (!is_array($document)) {
                        continue;
                    }

                    dotship_sql_write_document((string) $collection, $document, $pdo);
                }
            }

            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $throwable;
        }
    }

    function dotship_sql_read_collection(string $collection): array
    {
        $pdo = dotship_sql_pdo();
        $statement = $pdo->prepare('SELECT data FROM documents WHERE collection = :collection ORDER BY rowid ASC');
        $statement->execute([':collection' => $collection]);

        $documents = [];
        while ($row = $statement->fetch()) {
            $decoded = json_decode((string) ($row['data'] ?? ''), true);
            if (is_array($decoded)) {
                $documents[] = dotship_sql_rehydrate($decoded);
            }
        }

        return $documents;
    }

    function dotship_sql_write_document(string $collection, array $document, ?\PDO $pdo = null): void
    {
        $pdo ??= dotship_sql_pdo();
        $document = dotship_sql_normalize($document);
        $id = (string) ($document['_id']['value'] ?? $document['_id'] ?? '');
        if ($id === '' && isset($document['_id'])) {
            $id = (string) $document['_id'];
        }

        if ($id === '') {
            $id = (string) new \DotShipSqlStore\ObjectId();
            $document['_id'] = ['__dotship_type' => 'ObjectId', 'value' => $id];
        }

        $payload = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $nowMs = (new \DotShipSqlStore\UTCDateTime())->getMilliseconds();
        $statement = $pdo->prepare('INSERT INTO documents (collection, document_id, data, created_ms, updated_ms) VALUES (:collection, :document_id, :data, :created_ms, :updated_ms) ON CONFLICT(collection, document_id) DO UPDATE SET data = excluded.data, updated_ms = excluded.updated_ms');
        $statement->execute([
            ':collection' => $collection,
            ':document_id' => $id,
            ':data' => $payload !== false ? $payload : '{}',
            ':created_ms' => $nowMs,
            ':updated_ms' => $nowMs,
        ]);
    }

    function dotship_sql_delete_document(string $collection, string $documentId): void
    {
        if ($documentId === '') {
            return;
        }

        $pdo = dotship_sql_pdo();
        $statement = $pdo->prepare('DELETE FROM documents WHERE collection = :collection AND document_id = :document_id');
        $statement->execute([
            ':collection' => $collection,
            ':document_id' => $documentId,
        ]);
    }

    function dotship_sql_normalize(mixed $value): mixed
    {
        if ($value instanceof \DotShipSqlStore\ObjectId) {
            return ['__dotship_type' => 'ObjectId', 'value' => (string) $value];
        }

        if ($value instanceof \DotShipSqlStore\UTCDateTime) {
            return ['__dotship_type' => 'UTCDateTime', 'value' => $value->getMilliseconds()];
        }

        if ($value instanceof \DotShipSqlStore\Regex) {
            return ['__dotship_type' => 'Regex', 'pattern' => $value->pattern, 'flags' => $value->flags];
        }

        if ($value instanceof \ArrayObject) {
            return dotship_sql_normalize($value->getArrayCopy());
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = dotship_sql_normalize($item);
            }
            return $normalized;
        }

        return $value;
    }

    function dotship_sql_rehydrate(mixed $value): mixed
    {
        if (is_array($value) && isset($value['__dotship_type'])) {
            return match ($value['__dotship_type']) {
                'ObjectId' => new \DotShipSqlStore\ObjectId((string) ($value['value'] ?? '')),
                'UTCDateTime' => new \DotShipSqlStore\UTCDateTime((int) ($value['value'] ?? 0)),
                'Regex' => new \DotShipSqlStore\Regex((string) ($value['pattern'] ?? ''), (string) ($value['flags'] ?? '')),
                default => $value,
            };
        }

        if (is_array($value)) {
            $rehydrated = [];
            foreach ($value as $key => $item) {
                $rehydrated[$key] = dotship_sql_rehydrate($item);
            }
            return $rehydrated;
        }

        return $value;
    }

    function dotship_sql_document_matches(array $document, array $filter): bool
    {
        if ($filter === []) {
            return true;
        }

        foreach ($filter as $key => $expected) {
            if ($key === '$or') {
                $matched = false;
                foreach ((array) $expected as $subFilter) {
                    if (dotship_sql_document_matches($document, (array) $subFilter)) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    return false;
                }

                continue;
            }

            $actual = $document[$key] ?? null;

            if (is_array($expected) && array_key_exists('$in', $expected)) {
                $matched = false;
                foreach ((array) $expected['$in'] as $candidate) {
                    if (dotship_sql_values_equal($actual, $candidate)) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    return false;
                }

                continue;
            }

            if ($expected instanceof \DotShipSqlStore\Regex) {
                $pattern = '/' . $expected->pattern . '/' . $expected->flags;
                if (!preg_match($pattern, (string) $actual)) {
                    return false;
                }
                continue;
            }

            if (!dotship_sql_values_equal($actual, $expected)) {
                return false;
            }
        }

        return true;
    }

    function dotship_sql_values_equal(mixed $actual, mixed $expected): bool
    {
        if ($actual instanceof \DotShipSqlStore\ObjectId || $expected instanceof \DotShipSqlStore\ObjectId) {
            return (string) $actual === (string) $expected;
        }

        if ($actual instanceof \DotShipSqlStore\UTCDateTime && $expected instanceof \DotShipSqlStore\UTCDateTime) {
            return $actual->getMilliseconds() === $expected->getMilliseconds();
        }

        if ((is_object($actual) && method_exists($actual, '__toString')) || (is_object($expected) && method_exists($expected, '__toString'))) {
            return (string) $actual === (string) $expected;
        }

        return $actual === $expected;
    }

    function dotship_env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return $value === false || $value === null || $value === '' ? $default : $value;
    }

    function dotship_config(): array
    {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        $config = [
            'app_name' => 'DOT SHIP',
            'app_tagline' => 'Fast. Smart. Reliable.',
            'sqlite_path' => dotship_sql_store_path(),
            'formspree_endpoint' => dotship_env('DOTSHIP_FORMSPREE_ENDPOINT', 'https://formspree.io/f/xwpbardz'),
            'admin_email' => dotship_env('DOTSHIP_ADMIN_EMAIL', 'admin@dotship.local'),
            'admin_password' => dotship_env('DOTSHIP_ADMIN_PASSWORD', 'Admin@1234'),
            'demo_email' => dotship_env('DOTSHIP_DEMO_EMAIL', 'demo@dotship.local'),
            'demo_password' => dotship_env('DOTSHIP_DEMO_PASSWORD', 'Demo@1234'),
            'seed_demo' => filter_var(dotship_env('DOTSHIP_SEED_DEMO', '1'), FILTER_VALIDATE_BOOLEAN),
        ];

        return $config;
    }

    function dotship_client(): \DotShipSqlStore\Client
    {
        static $client = null;

        if ($client === null) {
            $client = new \DotShipSqlStore\Client('sqlite:' . dotship_config()['sqlite_path']);
        }

        return $client;
    }

    function dotship_db(): \DotShipSqlStore\Database
    {
        static $db = null;

        if ($db === null) {
            $db = dotship_client()->selectDatabase('dot_ship');
        }

        return $db;
    }

    function dotship_collection(string $name): \DotShipSqlStore\Collection
    {
        return dotship_db()->selectCollection($name);
    }
}