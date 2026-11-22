// Menu mobile et démo intégrée : le site fonctionne sans ce fichier (liens ordinaires).
(function () {
  var btn = document.querySelector('.menu')
  var nav = document.getElementById('nav')
  if (btn && nav) {
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') === 'true'
      btn.setAttribute('aria-expanded', String(!open))
      nav.classList.toggle('open', !open)
    })
  }
  var frame = document.querySelector('.frame[data-demo]')
  var launch = frame && frame.querySelector('[data-launch]')
  if (launch) {
    launch.addEventListener('click', function (e) {
      e.preventDefault()
      var f = document.createElement('iframe')
      f.src = frame.getAttribute('data-demo')
      f.title = frame.getAttribute('data-title')
      f.loading = 'lazy'
      frame.classList.add('live')
      frame.replaceChildren(f)
      f.focus()
    })
  }
})()
