// DOT SHIP animations initializer — GSAP + Lenis + microinteractions
(function(){
  function safeLoad(fn){try{fn()}catch(e){console.warn('anim init failed',e)}}

  safeLoad(()=>{
    // Lenis smooth scroll
    if (window.Lenis) {
      const lenis = new Lenis({duration:1.2,easing:(t)=>Math.min(1,1-Math.pow(1-t,3))});
      function raf(time){lenis.raf(time);requestAnimationFrame(raf)}
      requestAnimationFrame(raf);
    }

    // GSAP entrance animations for elements with .motion-fade-up, .motion-pop
    if (window.gsap) {
      const fadeEls = document.querySelectorAll('.motion-fade-up, .motion-anim');
      fadeEls.forEach(el => {
        gsap.fromTo(el, {y:18,opacity:0},{y:0,opacity:1,duration:0.7,ease:'power3.out',scrollTrigger:{trigger:el,start:'top 85%'}});
      });

      const popEls = document.querySelectorAll('.motion-pop');
      popEls.forEach(el => gsap.fromTo(el,{scale:0.98,opacity:0},{scale:1,opacity:1,duration:0.7,ease:'back.out(1.2)',scrollTrigger:{trigger:el,start:'top 85%'}}));
    }

    // small micro interactions: button press feedback
    document.querySelectorAll('.btn-primary, .btn-primary-gradient').forEach(btn=>{
      btn.addEventListener('pointerdown',()=>{btn.style.transform='scale(.995)';btn.style.boxShadow='0 10px 30px rgba(2,6,23,0.08)'});
      btn.addEventListener('pointerup',()=>{btn.style.transform='';btn.style.boxShadow=''});
      btn.addEventListener('pointerleave',()=>{btn.style.transform='';btn.style.boxShadow=''});
    });
  });
})();
