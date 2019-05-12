
var x = document.currentScript.parentNode.parentNode;
x.style.visibility = "hidden";
x.style.padding = "0px";
x.style.margin = "0px";
x.style.height = "0px";
var y = x.parentNode;
y.className = "ipsContainer container nestedEven ipsVariable ipsVariableProfile[~Intensity.100]";
var z = y.childNodes[0].childNodes[2];
z.innerHTML = "<div class=\"ipsContainer slider\"><i class=\"iconSpinner iconSmallSpinner throbber\"></i><div id=\"" + InstanceID + "\"><div class=\"background\" style=\"background-image: linear-gradient(to right, red, orange, yellow, green, blue, indigo, violet);\"></div><div class=\"bar\" style=\"width: 50%; outline: 5px; border-right-color: white; border-right-width: 7px; border-right-style: dotted;\"></div></div></div>";
z.childNodes[0].childNodes[1].addEventListener('mousedown', mySliderMove);
z.childNodes[0].childNodes[1].addEventListener('mousedown', function (e) {
    stopMySliderTimer(this.id);
    this.addEventListener('mousemove', mySliderMove);
});
z.childNodes[0].childNodes[1].addEventListener('mouseup', function (e) {
    startMySliderTimer(this.id);
    this.removeEventListener('mousemove', mySliderMove);
});
z.childNodes[0].childNodes[1].addEventListener('mouseup', myHueSliderSend);
startMySliderTimer(InstanceID);


