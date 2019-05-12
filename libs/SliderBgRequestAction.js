function mySliderBgRequestValue(InstanceID)
{
    var oReq = new XMLHttpRequest();
    oReq.addEventListener('loadend', mySliderBgRequestValueLoadEnd);
    o = {url: "hook/Yeelight" + InstanceID + "?action=GetValue&ident=bg_hue"};
    oReq.open('GET', o.url, true);
    oReq.send();
}
function mySliderBgRequestValueLoadEnd()
{
    if (this.status >= 200 && this.status < 300)
    {
        var response = this.responseText;
        if (response.startsWith("OK")) {
            var data = response.split(",");
            setSliderBg(data[1], data[2]);
        }
    }
    startMySliderBgTimer(data[1]);
}

function mySliderBgRequestActionGet(o)
{
    var oReq = new XMLHttpRequest();
    oReq.addEventListener('loadend', mySliderBgRequestActionGetLoadEnd);
    oReq.open('GET', o.url, true);
    oReq.send();
}

function mySliderBgRequestActionGetLoadEnd()
{
    if (this.status >= 200 && this.status < 300)
    {
        if (this.responseText !== "OK") {
            sendError(this.responseText);
        }
    } else {
        sendError(this.statusText);
    }
}

function sendError(data)
{
    var notify = document.getElementsByClassName("ipsNotifications")[0];
    var newDiv = document.createElement("div");
    newDiv.innerHTML = '<div style="height:auto; visibility: hidden; overflow: hidden; transition: height 500ms ease-in 0s" class="ipsNotification"><div class="spacer"></div><div class="message icon error" onclick="document.getElementsByClassName(\'ipsNotifications\')[0].removeChild(this.parentNode);"><div class="ipsIconClose"></div><div class="content"><div class="title">Fehler</div><div class="text">' + data + '</div></div></div></div>';
    if (notify.childElementCount === 0)
        var thisDiv = notify.appendChild(newDiv.firstChild);
    else
        var thisDiv = notify.insertBefore(newDiv.firstChild, notify.childNodes[0]);
    var newheight = window.getComputedStyle(thisDiv, null)["height"];
    thisDiv.style.height = "0px";
    thisDiv.style.visibility = "visible";
    function sleep(time) {
        return new Promise((resolve) => setTimeout(resolve, time));
    }
    sleep(10).then(() => {
        thisDiv.style.height = newheight;
    });
}
