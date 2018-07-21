var httpRequest = new XMLHttpRequest();
var lastResponse = "";

while(true) {
    httpRequest.open('GET', 'plain.txt', false);
    httpRequest.send();

    if (httpRequest.responseText != lastResponse) {
      lastResponse = httpRequest.responseText;
      self.postMessage(lastResponse);
    }
}
