var worker = new Worker('worker.js');

worker.addEventListener('message', updatePage);
worker.addEventListener('message', scrollPage);

function updatePage(e) {
  document.getElementById("log").innerHTML = e.data
}

function scrollPage(e) {
  window.scrollTo(0,document.body.scrollHeight);
}

function stopFollowing() {
  worker.removeEventListener('message', scrollPage);
  document.getElementById("stopbutton").style.display='none';
  document.getElementById("startbutton").style.display='';
}

function startFollowing() {
  worker.addEventListener('message', scrollPage);
  document.getElementById("stopbutton").style.display='';
  document.getElementById("startbutton").style.display='none';
}

function setTitle() {
  var httpRequest = new XMLHttpRequest();
  httpRequest.open('GET', 'title', false);
  httpRequest.send();
  document.title = httpRequest.responseText;
}
