<?php
declare(strict_types=1);

namespace DotShipMongoCompat {
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
            $this->appendDocuments([$document]);
            return new InsertOneResult($document['_id']);
        }

        public function insertMany(array $documents): InsertManyResult
        {
            $insertedIds = [];
            $batch = [];

            foreach ($documents as $document) {
                $document['_id'] ??= new ObjectId();
                $insertedIds[] = $document['_id'];
                $batch[] = $document;
            }

            $this->appendDocuments($batch);
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
            $data = dotship_mongo_store_load();
            $collection = &$data[$this->database][$this->name];
            $collection = is_array($collection) ? $collection : [];

            $matched = 0;
            $modified = 0;

            foreach ($collection as $index => $document) {
                $rehydrated = dotship_mongo_rehydrate($document);
                if (!dotship_mongo_document_matches($rehydrated, $filter)) {
                    continue;
                }

                $matched++;
                $updated = $rehydrated;

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

                if ($updated != $rehydrated) {
                    $modified++;
                }

                $collection[$index] = dotship_mongo_normalize($updated);
                break;
            }

            dotship_mongo_store_save($data);
            return new UpdateResult($matched, $modified);
        }

        public function deleteOne(array $filter): DeleteResult
        {
            $data = dotship_mongo_store_load();
            $collection = &$data[$this->database][$this->name];
            $collection = is_array($collection) ? $collection : [];

            $deleted = 0;

            foreach ($collection as $index => $document) {
                $rehydrated = dotship_mongo_rehydrate($document);
                if (!dotship_mongo_document_matches($rehydrated, $filter)) {
                    continue;
                }

                unset($collection[$index]);
                $deleted = 1;
                break;
            }

            $collection = array_values($collection);
            dotship_mongo_store_save($data);
            return new DeleteResult($deleted);
        }

        private function appendDocuments(array $documents): void
        {
            if ($documents === []) {
                return;
            }

            $data = dotship_mongo_store_load();
            $data[$this->database][$this->name] = $data[$this->database][$this->name] ?? [];

            foreach ($documents as $document) {
                $data[$this->database][$this->name][] = dotship_mongo_normalize($document);
            }

            dotship_mongo_store_save($data);
        }

        private function readFiltered(array $filter): array
        {
            $data = dotship_mongo_store_load();
            $collection = $data[$this->database][$this->name] ?? [];
            $matched = [];

            foreach ($collection as $document) {
                $rehydrated = dotship_mongo_rehydrate($document);
                if (dotship_mongo_document_matches($rehydrated, $filter)) {
                    $matched[] = $rehydrated;
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
    if (!class_exists(\MongoDB\BSON\ObjectId::class)) {
        class_alias(\DotShipMongoCompat\ObjectId::class, \MongoDB\BSON\ObjectId::class);
    }

    if (!class_exists(\MongoDB\BSON\UTCDateTime::class)) {
        class_alias(\DotShipMongoCompat\UTCDateTime::class, \MongoDB\BSON\UTCDateTime::class);
    }

    if (!class_exists(\MongoDB\BSON\Regex::class)) {
        class_alias(\DotShipMongoCompat\Regex::class, \MongoDB\BSON\Regex::class);
    }

    if (!class_exists(\MongoDB\Client::class)) {
        class_alias(\DotShipMongoCompat\Client::class, \MongoDB\Client::class);
    }

    if (!class_exists(\MongoDB\Database::class)) {
        class_alias(\DotShipMongoCompat\Database::class, \MongoDB\Database::class);
    }

    if (!class_exists(\MongoDB\Collection::class)) {
        class_alias(\DotShipMongoCompat\Collection::class, \MongoDB\Collection::class);
    }

    if (!class_exists(\MongoDB\InsertOneResult::class)) {
        class_alias(\DotShipMongoCompat\InsertOneResult::class, \MongoDB\InsertOneResult::class);
    }

    if (!class_exists(\MongoDB\InsertManyResult::class)) {
        class_alias(\DotShipMongoCompat\InsertManyResult::class, \MongoDB\InsertManyResult::class);
    }

    if (!class_exists(\MongoDB\UpdateResult::class)) {
        class_alias(\DotShipMongoCompat\UpdateResult::class, \MongoDB\UpdateResult::class);
    }

    if (!class_exists(\MongoDB\DeleteResult::class)) {
        class_alias(\DotShipMongoCompat\DeleteResult::class, \MongoDB\DeleteResult::class);
    }

    function dotship_mongo_store_path(): string
    {
        return dirname(__DIR__) . '/storage/dotship-data.json';
    }

    function dotship_mongo_store_load(): array
    {
        $path = dotship_mongo_store_path();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    function dotship_mongo_store_save(array $data): void
    {
        $path = dotship_mongo_store_path();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    function dotship_mongo_normalize(mixed $value): mixed
    {
        if ($value instanceof \DotShipMongoCompat\ObjectId) {
            return ['__dotship_type' => 'ObjectId', 'value' => (string) $value];
        }

        if ($value instanceof \DotShipMongoCompat\UTCDateTime) {
            return ['__dotship_type' => 'UTCDateTime', 'value' => $value->getMilliseconds()];
        }

        if ($value instanceof \DotShipMongoCompat\Regex) {
            return ['__dotship_type' => 'Regex', 'pattern' => $value->pattern, 'flags' => $value->flags];
        }

        if ($value instanceof \ArrayObject) {
            return dotship_mongo_normalize($value->getArrayCopy());
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = dotship_mongo_normalize($item);
            }
            return $normalized;
        }

        return $value;
    }

    function dotship_mongo_rehydrate(mixed $value): mixed
    {
        if (is_array($value) && isset($value['__dotship_type'])) {
            return match ($value['__dotship_type']) {
                'ObjectId' => new \DotShipMongoCompat\ObjectId((string) ($value['value'] ?? '')),
                'UTCDateTime' => new \DotShipMongoCompat\UTCDateTime((int) ($value['value'] ?? 0)),
                'Regex' => new \DotShipMongoCompat\Regex((string) ($value['pattern'] ?? ''), (string) ($value['flags'] ?? '')),
                default => $value,
            };
        }

        if (is_array($value)) {
            $rehydrated = [];
            foreach ($value as $key => $item) {
                $rehydrated[$key] = dotship_mongo_rehydrate($item);
            }
            return $rehydrated;
        }

        return $value;
    }

    function dotship_mongo_document_matches(array $document, array $filter): bool
    {
        if ($filter === []) {
            return true;
        }

        foreach ($filter as $key => $expected) {
            if ($key === '$or') {
                $matched = false;
                foreach ((array) $expected as $subFilter) {
                    if (dotship_mongo_document_matches($document, (array) $subFilter)) {
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
                    if (dotship_mongo_values_equal($actual, $candidate)) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    return false;
                }

                continue;
            }

            if ($expected instanceof \DotShipMongoCompat\Regex) {
                $pattern = '/' . $expected->pattern . '/' . $expected->flags;
                if (!preg_match($pattern, (string) $actual)) {
                    return false;
                }
                continue;
            }

            if (!dotship_mongo_values_equal($actual, $expected)) {
                return false;
            }
        }

        return true;
    }

    function dotship_mongo_values_equal(mixed $actual, mixed $expected): bool
    {
        if ($actual instanceof \DotShipMongoCompat\ObjectId || $expected instanceof \DotShipMongoCompat\ObjectId) {
            return (string) $actual === (string) $expected;
        }

        if ($actual instanceof \DotShipMongoCompat\UTCDateTime && $expected instanceof \DotShipMongoCompat\UTCDateTime) {
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
            'mongo_uri' => dotship_env('MONGODB_URI', 'mongodb://127.0.0.1:27017'),
            'mongo_db' => dotship_env('MONGODB_DB', 'dot_ship'),
            'formspree_endpoint' => dotship_env('DOTSHIP_FORMSPREE_ENDPOINT', 'https://formspree.io/f/xwpbardz'),
            'admin_email' => dotship_env('DOTSHIP_ADMIN_EMAIL', 'admin@dotship.local'),
            'admin_password' => dotship_env('DOTSHIP_ADMIN_PASSWORD', 'Admin@1234'),
            'demo_email' => dotship_env('DOTSHIP_DEMO_EMAIL', 'demo@dotship.local'),
            'demo_password' => dotship_env('DOTSHIP_DEMO_PASSWORD', 'Demo@1234'),
            'seed_demo' => filter_var(dotship_env('DOTSHIP_SEED_DEMO', '1'), FILTER_VALIDATE_BOOLEAN),
        ];

        return $config;
    }

    function dotship_client(): \MongoDB\Client
    {
        static $client = null;

        if ($client === null) {
            $client = new \MongoDB\Client(dotship_config()['mongo_uri']);
        }

        return $client;
    }

    function dotship_db(): \MongoDB\Database
    {
        static $db = null;

        if ($db === null) {
            $db = dotship_client()->selectDatabase(dotship_config()['mongo_db']);
        }

        return $db;
    }

    function dotship_collection(string $name): \MongoDB\Collection
    {
        return dotship_db()->selectCollection($name);
    }
}