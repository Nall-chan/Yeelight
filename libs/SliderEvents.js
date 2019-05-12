function mySliderMove(event) {
    var rect = this.getBoundingClientRect();
    var pro = (event.clientX - rect.left) / rect.width * 100;
    this.childNodes[1].style.width = pro + "%";
}

function myHueSliderSend(event) {
    var rect = this.getBoundingClientRect();
    var hue = (event.clientX - rect.left) / rect.width * 360;
    mySliderRequestActionGet({url: "hook/Yeelight" + this.id + "?action=SetValue&ident=hue&value=" + hue});
}

function setSlider(id, hue) {
    var div = document.getElementById(id);
    var bar = div.childNodes[1];
    var pro = (hue / 3.6);
    bar.style.width = pro + "%";
}

function startMySliderTimer(target) {
    var element = document.getElementById(target);
    element.timerId = window.setTimeout(mySliderRequestValue, 1000, target);
}
function stopMySliderTimer(target) {
    var element = document.getElementById(target);
    clearTimeout(element.timerId);
}