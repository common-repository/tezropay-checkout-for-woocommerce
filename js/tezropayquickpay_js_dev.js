// Tezro Instant Pay
var orderArray = [];
var instantPay = async function(e) {
  var json = JSON.parse(e.getAttribute("data-json"));
  if (orderArray.includes(json.orderId)) {

  }else{
    currentProductId = json.orderId;
    orderArray.push(json.orderId);
    tezroApiInstantPay.runExtButtonSpinner(e, json.orderId);
    tezroApiInstantPay.payInit(e, json);
  }
};
var buyNow = async function(e) {
  var json = JSON.parse(e.getAttribute("data-json"));
  if (orderArray.includes(json.orderId)) {

  }else{
    currentProductId = json.orderId;
    orderArray.push(json.orderId);
    //tezroApiInstantPay.runExtButtonSpinner(e, json.orderId);
    tezroApiInstantPay.payBuyNow(e, json);
  }
};
var isOpen = false;
let currentEvent;
let reqTimeout;
let reqInterval;
let clearIntervalTimeout;
let initTimeout;
let tezroPayQRContainer;
let qr;
let currentLink;
let currentOrderId;
let confirmStatusUrl;
let currentProductId;

const tezroApiInstantPay = {
  runExtButtonSpinner: async function(event,id) {
    /*
    if (isOpen) {
        tezroApiInstantPay.close(`+currentProductId+`);
        tezroApiInstantPay.runExtButtonSpinner(event);
    }else{
    */
      isOpen = true;
      currentEvent = event;
      //currentProductId = id;
      var button;
      var style = ""
      if (document.getElementById('tezroInstantPay'+id)) {
          button = document.getElementById('tezroInstantPay'+id);
      }else{
          button = document.getElementById(event.target.id);
          style = "display:none;"
      }
      const buttonHeight = button.getBoundingClientRect().height;
      const windowWidth = document.body.clientWidth;
      const buttonWidth = button.getBoundingClientRect().width;
      const buttonX = button.getBoundingClientRect().x;
      const buttonSide = button.getBoundingClientRect().x < (windowWidth / 2) ? 'left' : 'right';
      const btnWrapper = document.createElement('div');
      btnWrapper.setAttribute('id', 'tezroPayBtnWrapper'+id);
      btnWrapper.setAttribute('class', 'tezroPayBtnWrapperClass');
      btnWrapper.style.position = 'relative';
      btnWrapper.style.width = buttonWidth;
      btnWrapper.style.marginTop = '-190px';
      btnWrapper.style.height = '100%';

      createSpinnerAnimationStyles();

      button.insertAdjacentHTML('beforeend', externalSpinner(buttonHeight));

      if (!!tezroApiInstantPay.isMobile()) {
        btnWrapper.insertAdjacentHTML('beforeend', `<div id="tezroPayQRContainer`+id+`" class="tezroPayQRContainerFrame" style="word-break: break-word; cursor: auto; display: flex; flex-direction: column; justify-content: center; align-items: center; border-radius: 10px; padding: 5px 5px 20px 5px; z-index: 1; margin-bottom: 10px; bottom: 100%; min-height: 150px; background-color: #fff;">
          <button class="tezroPayQRContainerButton" onclick='tezroApiInstantPay.close(`+id+`);' style='`+style+`pointer-events: auto; width: 30px; height: 30px; border-radius: 20px; box-shadow: 0px 0px 8px rgba(0,0,0,0.2); position: absolute; z-index: 2; top: -15px; right: -15px; border: none; background-color: #fff; font-size: 14px; font-weight: 700; color: #2C3036'>X</button>
          <div id="tezroPayQRBox`+id+`" style="display: inline-block;">
            <button style='background: transparent; border: none;' onclick='openLink(`+id+`);' id='tezroPayQRMobileButton`+id+`'></button>
          </div>
        </div>`);
      } else {
        btnWrapper.insertAdjacentHTML('beforeend', `<div id="tezroPayQRContainer`+id+`" class="tezroPayQRContainerFrame" style="word-break: break-word; cursor: auto; display: flex; flex-direction: column; justify-content: center; align-items: center; border-radius: 10px; padding: 5px 5px 20px 5px; z-index: 1; margin-bottom: 10px; bottom: 100%; min-height: 150px; background-color: #fff;">
          <button class="tezroPayQRContainerButton" onclick='tezroApiInstantPay.close(`+id+`);' style='`+style+`pointer-events: auto; width: 30px; height: 50px; border-radius: 20px; box-shadow: 0px 0px 8px rgba(0,0,0,0.2); position: absolute; z-index: 2; top: -15px; right: -15px; border: none; background-color: #fff; font-size: 14px; font-weight: 700; color: #2C3036'>X</button>
          <div id="tezroPayQRBox`+id+`" style="display: inline-block;"></div>
        </div>`);
      }
      wrap(button, btnWrapper);
       // todo uniq id
      tezroPayQRContainer = document.getElementById('tezroPayQRContainer'+id);

      tezroPayQRContainer.style.margin = 'auto';
      tezroPayQRContainer.style.display = 'none';
      if (document.getElementById('tezroInstantPay'+id)) {
          tezroPayQRContainer.style.width = '100%';
      }else{
          tezroPayQRContainer.style.width = '100%';
      }
      tezroPayQRContainer.style.animationName = 'slider';
      tezroPayQRContainer.style.animationDuration = '500ms';
      tezroPayQRContainer.style.animationFillMode = 'forwards';
      tezroPayQRContainer.style.boxShadow = 'rgba(0, 0, 0, 0.2) 0px 0px 13px'

      if (buttonSide === 'left' && buttonX < 150) {
        if (buttonX < Math.abs(buttonX - buttonWidth)) {
          tezroPayQRContainer.style.left = buttonWidth < 150 ? '0' : '9px';
        } else {
          tezroPayQRContainer.style.left = Math.abs(buttonX - buttonWidth);
        }
      } else {
        if ((buttonX + buttonWidth) >= windowWidth) {
          tezroPayQRContainer.style.right = '0';
        } else {
          tezroPayQRContainer.style.right = 500;
        }
      }

    //}
  },
  stopExtButtonSpinner: function(id) {
    const spinnerExternalEl = document.getElementById('tezroPayExternalButtonSpinner'+id);
    spinnerExternalEl && spinnerExternalEl.parentNode.removeChild(spinnerExternalEl);
  },
  payInit: async function(event,params) {
    try {
        await initWidget(params, event);
    } catch (error) {
        console.log(error);
    }
  },
  payBuyNow: async function(event,params,json) {
    try {
        await initBuyNow(params,json,event);
    } catch (error) {
        console.log(error);
    }
  },
  checkoutInit: async function(event,params,json) {
    try {
        await checkoutWidget(params,json,event);
    } catch (error) {
        console.log(error);
    }
  },
  isMobile: function() {
    return navigator.appVersion.indexOf("Mobile")>-1;
  },
  isIOS: function() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  },
  isAndroid: function() {
    return navigator.appVersion.indexOf("Android")>-1;
  },
  close: function(closeId) {
    var index = orderArray.indexOf(closeId);
    if (index > -1) {
        orderArray.splice(index, 1);
    }
    currentLink = null;
    !!qr && qr.clear();
    isOpen = false;
    currentEvent = null;
    const tezroPayBtnWrapper = document.getElementById('tezroPayBtnWrapper'+closeId);
    //tezroPayBtnWrapper.outerHTML = ""; ie >11
    if(tezroPayBtnWrapper){
      tezroPayBtnWrapper.parentNode.removeChild(tezroPayBtnWrapper);
    }
    const tezroPayQRContainer = document.getElementById('tezroPayQRContainer'+closeId);
    //tezroPayQRContainer.outerHTML = ""; ie >11
    if(tezroPayQRContainer){
      tezroPayQRContainer.parentNode.removeChild(tezroPayQRContainer);
    }
    tezroApiInstantPay.stopExtButtonSpinner(closeId);
    clearTimeout(reqTimeout);
    clearInterval(reqInterval);
    clearTimeout(clearIntervalTimeout);
  }
}

let initWidget = async function(params, event) {
  currentOrderId = params.orderId;
  currentEvent = event;
  let cssAnimation = document.createElement('style');
  cssAnimation.type = 'text/css';
  let containerAnimation = document.createTextNode('@-webkit-keyframes slider {'+
  'from { opacity: 0 }'+
  'to { opacity: 1 }'+
  '}');
  let spinnerAnimation = document.createTextNode('@-webkit-keyframes lds-ring {'+
  'from { transform: rotate(0deg) }'+
  'to { transform: rotate(360deg) }'+
  '}');
  cssAnimation.appendChild(containerAnimation);
  cssAnimation.appendChild(spinnerAnimation);
  document.getElementsByTagName("head")[0].appendChild(cssAnimation);
  try {
        const config = {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(params)
        }
        const response = await fetch(params.initTezro, config)
        const json = await response.json()
        currentLink = json.link;
        let container = document.getElementById('tezroPayQRContainer'+params.orderId);
        container.insertAdjacentHTML('afterbegin', titleContent(event.pointerId, json.link, params.orderId));
        if(!container){
          container = container.insertAdjacentHTML('afterbegin', titleContent(event.pointerId, json.link, params.orderId));
        }
        tezroPayQRContainer.style.display = 'flex';
        //console.log(json);
        confirmStatusUrl = params.confirmStatusUrl;
        await checkPaymentStatus(json.id, params.keyId, params.orderId, event);
        await generateQRCode(QRCode, json.link, params.orderId, event, json.eosName, json.totalAmount, json.currency, json.photos, json.name, json.id);
    } catch (error) {
        console.log(error);
        tezroApiInstantPay.close(params.orderId);
    }
  tezroApiInstantPay.stopExtButtonSpinner();
}
let initBuyNow = async function(params, event) {
  currentOrderId = params.orderId;
  currentEvent = event;
  try {
        const config = {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(params)
        }
        const response = await fetch(params.initTezro, config)
        const json = await response.json()
        currentLink = json.link;
        //console.log(json);
        confirmStatusUrl = params.confirmStatusUrl;
        if(tezroApiInstantPay.isMobile()){
          location.href = json.link;
        }else{
          window.open(json.link, "_blank");
        }
    } catch (error) {
        console.log(error);
        tezroApiInstantPay.close(params.orderId);
    }
  tezroApiInstantPay.stopExtButtonSpinner();
}
let checkoutWidget = async function(params,json,event) {
  currentOrderId = params.orderId;
  currentEvent = event;
  let cssAnimation = document.createElement('style');
  cssAnimation.type = 'text/css';
  let containerAnimation = document.createTextNode('@-webkit-keyframes slider {'+
  'from { opacity: 0 }'+
  'to { opacity: 1 }'+
  '}');
  let spinnerAnimation = document.createTextNode('@-webkit-keyframes lds-ring {'+
  'from { transform: rotate(0deg) }'+
  'to { transform: rotate(360deg) }'+
  '}');
  cssAnimation.appendChild(containerAnimation);
  cssAnimation.appendChild(spinnerAnimation);
  document.getElementsByTagName("head")[0].appendChild(cssAnimation);
  try {
        currentLink = json.link;
        let container = document.getElementById('tezroPayQRContainer'+params.orderId);
        container.insertAdjacentHTML('afterbegin', titleContent(event.pointerId, json.link, params.orderId));
        if(!container){
          container = container.insertAdjacentHTML('afterbegin', titleContent(event.pointerId, json.link, params.orderId));
        }
        tezroPayQRContainer.style.display = 'flex';
        //console.log(json);
        confirmStatusUrl = params.confirmStatusUrl;
        await checkPaymentStatus(json.id, params.keyId, params.orderId, event);
        await generateQRCode(QRCode, json.link, params.orderId, event, json.eosName, json.totalAmount, json.currency, json.photos, json.name, json.id);
    } catch (error) {
        console.log(error);
        tezroApiInstantPay.close(params.orderId);
    }
  tezroApiInstantPay.stopExtButtonSpinner();
}

async function generateQRCode(qrCode, qrLink,id, event,eosName,amount,currency,photos,name, uniqid) {
  currentEvent = event;
  let el = !!tezroApiInstantPay.isMobile() ? document.getElementById('tezroPayQRMobileButton'+id) : document.getElementById('tezroPayQRBox'+id);
  if (!!el) {
    var link = qrLink;
    qr = new qrCode(el, {
      text: link,
      width: 150,
      height: 150
    });
  }else{
    console.log("QR Code problem")
    openLink(id);
  }
}

function openLink(id) {
  const link = document.getElementById('tezropaylink'+id).value;
  window.open(link, '_blank');
}

function wrap(el, wrapper) {
  el.insertAdjacentHTML('afterEnd', wrapper.outerHTML);
}

async function checkPaymentStatus(orderId, keyId, productId, event) {
  let tezroPayQRBox = document.getElementById('tezroPayQRBox'+productId);
  let tezroPayTitleBox = document.getElementById('tezroPayTitleBox'+productId);
  currentEvent = event;
  reqInterval = setInterval(async () => {
    const currentDate = new Date();
    const statusRes = await fetch(
      `https://openapi.tezro.com/api/v1/orders/${orderId}`, {
        method: 'GET',
        headers: {
          'KeyID': keyId,
          'Timestamp': Date.now()
        }
      }
    ).then(res => res.json());

    // Instant pay
    let tezropay_instant_pay = async function(statusRes, id){
      try {
          const config = {
              method: 'POST',
              headers: {
                  'Accept': 'application/json',
                  'Content-Type': 'application/json',
              },
              body: JSON.stringify(statusRes)
          }
          const response = await fetch(confirmStatusUrl, config)
          const json = await response.json()
          console.log(json);
          await generateQRCode(QRCode, json.link, params.orderId, event);
      } catch (error) {
           //console.log(error);
      }
    }

    switch(statusRes.status) {
      case 'order_created': {
        currentLink = statusRes.link;
        break;
      }
      case 'address_confirmed': {
        tezroPayQRContainer.style.display = 'flex';
        const expiresAtDateISO = new Date(statusRes.expiresAt).toISOString();
        const expiresAtDate = new Date(statusRes.expiresAt).toLocaleDateString();
        const expiresAtTime = new Date(statusRes.expiresAt).toLocaleTimeString();

        /*
        var element = document.getElementById("tezroPayTitleBox"+productId);
        if(element){
          element.parentNode.removeChild(element);
        }
        var element2 = document.getElementById("tezroPayQRBox"+productId);
        if(element2) {
          element2.parentNode.removeChild(element2);
        }
        */
        var element3 = document.getElementById("addressConfirmedContent"+productId);
        if(!element3) {
          tezroPayQRContainer && tezroPayQRContainer.insertAdjacentHTML('beforeend', addressConfirmedContent(event.pointerId, statusRes.expiresAt, productId));
        }
          if(statusRes.expiresAt){
          const expiresAtDateISO = new Date(statusRes.expiresAt).toISOString();
              initializeClock('timerCountdown', expiresAtDateISO);
          }
        tezropay_instant_pay(statusRes,productId);
        break;
      }

      case 'order_payed': {
        tezroPayQRContainer.style.display = 'flex';
        clearInterval(reqInterval);
        clearTimeout(clearIntervalTimeout);

        var element = document.getElementById("tezroPayTitleBox"+productId);
        if(element){
          element.parentNode.removeChild(element);
        }
        var element2 = document.getElementById("tezroPayQRBox"+productId);
        if(element2) {
          element2.parentNode.removeChild(element2);
        }
        var element3 = document.getElementById("addressConfirmedContent"+productId);
        if(element3){
          element3.parentNode.removeChild(element3);
        }
        var element4 = document.getElementById("tezroPayPaidContent"+productId);
        if(!element4){
            tezroPayQRContainer && tezroPayQRContainer.insertAdjacentHTML('beforeend', paidContent(event.pointerId,productId));
        }
        tezropay_instant_pay(statusRes,productId);
        break;
      }

      case 'order_confirmed': {
        tezroPayQRContainer.style.display = 'flex';
        clearInterval(reqInterval);
        clearTimeout(clearIntervalTimeout);

        var element = document.getElementById("tezroPayTitleBox"+productId);
        if(element){
          element.parentNode.removeChild(element);
        }
        var element2 = document.getElementById("tezroPayQRBox"+productId);
        if(element2) {
          element2.parentNode.removeChild(element2);
        }
        var element3 = document.getElementById("addressConfirmedContent"+productId);
        if(element3){
          element3.parentNode.removeChild(element3);
        }
        var element4 = document.getElementById("tezroPayPaidContent"+productId);
        if(!element4){
            tezroPayQRContainer && tezroPayQRContainer.insertAdjacentHTML('beforeend', paidContent(event.pointerId,productId));
        }
        tezropay_instant_pay(statusRes,productId);
        break;
      }

      case 'order_expired': {
        const currentDDMMYYYY = currentDate.toLocaleDateString();
        const currentHHMMSS = currentDate.toLocaleTimeString();
        const formattedExpiresAtDate = new Date(expiresAtDate + ' ' + expiresAtTime);
        const formattedCurrentDate = new Date(currentDDMMYYYY + ' ' + currentHHMMSS);

        if (formattedExpiresAtDate < formattedCurrentDate) {
          tezroApiInstantPay.close(currentProductId);
        }
        
        break;
      }

      default: 
      tezroApiInstantPay.close(productId);
      return
    }
  }, 2000);
  
  clearIntervalTimeout = setTimeout(() => {
    clearInterval(reqInterval);
  }, 900000);
}

function createSpinnerAnimationStyles() {
  let cssAnimation = document.createElement('style');
  cssAnimation.type = 'text/css';
  let spinnerAnimation = document.createTextNode('@-webkit-keyframes lds-ring {'+
  'from { transform: rotate(0deg) }'+
  'to { transform: rotate(360deg) }'+
  '}');
  cssAnimation.appendChild(spinnerAnimation);
  document.getElementsByTagName("head")[0].appendChild(cssAnimation);
}

function getTimeRemaining(endtime){
  const total = Date.parse(endtime) - Date.parse(new Date());
  const seconds = Math.floor( (total/1000) % 60 );
  const minutes = Math.floor( (total/1000/60) % 60 );
  const hours = Math.floor( (total/(1000*60*60)) % 24 );
  const days = Math.floor( total/(1000*60*60*24) );

  return {
    total,
    days,
    hours,
    minutes: minutes < 10 ? '0' + minutes : minutes,
    seconds: seconds < 10 ? '0' + seconds : seconds
  };
}

function initializeClock(id, endtime) {
  const clock = document.getElementById(id);
  const timeinterval = setInterval(() => {
    const t = getTimeRemaining(endtime);
    clock.innerText = `${t.minutes} : ${t.seconds}`;
    if (t.total <= 0) {
      clearInterval(timeinterval);
    }
  },1000);
}

let externalSpinner = function(size) {
  const spinnerSize = size > 30 ? size - 15 : size - 5;

  return `<div id='tezroPayExternalButtonSpinner`+currentProductId+`' style='position: absolute; 
    top: 0; 
    left: 0; 
    right: 0; 
    bottom: 0; 
    z-index: 0; 
    background: linear-gradient(45deg, #2C3036, #575F6B);
    display: flex;
    justify-content: center;
    align-items: center;'
  >
      <div class='lds-ring' style="display: inline-block;
        position: relative;
        width: ${spinnerSize}px;
        height: ${spinnerSize}px;"
        display: flex;
        justify-content: center;
        align-items: center;"
      >
        <div style='box-sizing: border-box;
          display: block;
          position: absolute;
          width: ${spinnerSize - 2}px;
          height: ${spinnerSize - 2}px;
          border: 2px solid #fff;
          border-radius: 50%;
          animation: lds-ring 1.2s linear infinite;
          border-color: #fff #fff transparent transparent;'>
        </div>
      </div>
  </div>`
}
let titleContent = (currentTarget, link, id) => currentTarget === currentEvent.pointerId && `<div class="tezroPayTitleBoxClass" id="tezroPayTitleBox`+id+`" style='width: 100%; display: flex; justify-content: center;'>
            <input id='tezropaylink`+id+`' hidden value='${link}'>
            <a href="`+link+`" target="_blank"><p style='font-size: 16px; font-weight: 700; color: #2C3036; margin-bottom: 10px;line-height: normal;'>${!!tezroApiInstantPay.isMobile() ? 'Click and Pay' : 'Scan QR and Pay without sign up'}</p></a>
          </div>`

let addressConfirmedContent = (currentTarget, expiresAt, id) => currentTarget === currentEvent.pointerId && `<div id='addressConfirmedContent`+id+`' style='display: flex; flex-direction: column; align-items: center;'>
  <h3 style='color: #2c3036; font-size: 16px; text-align: center;'>Awaiting your payment</h3>
  <p style='margin-top: 7px; text-align: center; color: #828282; font-size: 12px; font-weight: 700;'>Without payment, your order will be cancelled after</p>
  <div style='align-self: stretch; min-height: 40px; display: flex; justify-content: center; align-items: center; background: #2c3036; border-radius: 50px; padding: 5px; margin-top: 1px;'>
    <p id='timerCountdown' style='margin-bottom: 0; font-size: 16px; color: #ffffff; font-weight: 700; text-align: center;'></p>
  </div>
</div>`

let paidContent = (currentTarget, id) => currentTarget === currentEvent.pointerId && `<div id='tezroPayPaidContent`+id+`' style='display: flex; flex-direction: column; justify-content: center; align-items: center;'>
  <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50" viewBox="0 0 64.341 64.341">
    <defs>
      <linearGradient id="linear-gradient" x1="0.5" y1="0.5" x2="0.145" gradientUnits="objectBoundingBox">
        <stop offset="0" stop-color="#575f6b"/>
        <stop offset="1" stop-color="#2c3036"/>
      </linearGradient>
    </defs>
    <g id="Group_12256" data-name="Group 12256" transform="translate(-703.5 -283.5)">
      <path id="Path_21429" data-name="Path 21429" d="M749.671,305.621a2.474,2.474,0,0,1,0,3.5l-16.6,16.6a2.475,2.475,0,0,1-3.5,0l-7.9-7.9a2.474,2.474,0,0,1,3.5-3.5l6.152,6.152,14.849-14.85a2.475,2.475,0,0,1,3.5,0Zm17.671,10.049A31.671,31.671,0,1,1,735.671,284a31.653,31.653,0,0,1,31.671,31.671Zm-4.948,0a26.722,26.722,0,1,0-26.722,26.722,26.707,26.707,0,0,0,26.722-26.722Zm0,0" stroke="rgba(0,0,0,0)" stroke-width="1" fill="url(#linear-gradient)"/>
    </g>
  </svg>

  <p style='color: #2c3036; font-size: 16px; font-weight: 700; margin-top: 0px;'>Paid</p>
  <p style='color: #2c3036; font-size: 8px; font-weight: 700; margin-top: 0px;'>Please, unlock payment in Tezro App after you received your order.</p>
  <button onclick='event.stopPropagation(); tezroApiInstantPay.close(`+currentProductId+`);' style='pointer-events: auto; background: linear-gradient(45deg, #2C3036, #575F6B); width: 80px; padding: 5px; font-weight: 700; border-radius: 20px; height: 30px; font-size: 12px; margin-top: 1px; border: none; color: #fff;'>OK</button>
</div>`;