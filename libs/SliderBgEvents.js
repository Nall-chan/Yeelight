function mySliderBgMove(event) {
    var rect = this.getBoundingClientRect();
    var pro = (event.clientX - rect.left) / rect.width * 100;
    this.childNodes[1].style.width = pro + "%";
}

function myHueSliderBgSend(event) {
    var rect = this.getBoundingClientRect();
    var hue = (event.clientX - rect.left) / rect.width * 360;
    mySliderRequestActionGet({url: "hook/Yeelight" + this.id + "?action=SetValue&ident=bg_hue&value=" + hue});
}

function setSliderBg(id, hue) {
    var div = document.getElementById(id);
    var bar = div.childNodes[1];
    var pro = (hue / 3.6);
    bar.style.width = pro + "%";
}

function startMySliderBgTimer(target) {
    var element = document.getElementById(target);
    element.timerId = window.setTimeout(mySliderBgRequestValue, 1000, target);
}
function stopMySliderBgTimer(target) {
    var element = document.getElementById(target);
    clearTimeout(element.timerId);
}