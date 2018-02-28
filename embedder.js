/* Copyright Music Education OY (Estonia), 2015 */
"use strict"; 
var body = document.getElementsByTagName("body")[0], html = document.getElementsByTagName("html")[0]; 
window.mms_resizer = function(a){
    a.setAttribute("allowfullscreen", "allowfullscreen"), 
    /*a.setAttribute("scrolling", "no"),*/
    window.addEventListener("message", function(b){
        /*console.log("GOT MESSAGE", b.data); */
        a.setAttribute("scrolling", "no");
        var c = b.data; 
        if ("matchmysound-embed-resize" == c.id)
            a.height = c.height, $(a).css({height:c.height, overflow:"hidden"});
        else if ("matchmysound-embed-close" == c.id)
            a.parentNode.removeChild(a);
        else if ("matchmysound-embed-scroll" == c.id){
            var d = window.innerHeight || document.documentElement.clientHeight, 
            e = Math.max(body.scrollTop, html.scrollTop), 
            f = a.offsetTop + c.top - e, 
            g = f + c.height;
            if (0 > f || g > d || "force" == c.center){
                var h = 0; 
                h = c.center?(d - c.height) / 2 - f:0 > f? - f:d - g; 
                var i = e - h; 
                window.jQuery?window.$("body,html").animate({scrollTop:i}, 200, "linear"):body.scrollTop = html.scrollTop = i
            }
        }
    }),
    /*console.log("ASKING FOR SIZE"), */
    a.contentWindow.postMessage({id:"matchmysound-ask-size"}, "*")
};

