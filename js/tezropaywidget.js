/* eslint-disable no-undef */
let isOpen = false;
let currentEvent;
let button;
let reqTimeout;
let reqInterval;
let clearIntervalTimeout;
let initTimeout;
let tezroPayQRContainer;
let qr;
let currentLink;
let currentOrderId;

const tezroApi = {
  runExtButtonSpinner: function(event) {
    if (!isOpen) {
      isOpen = true;
      currentEvent = event;
      button = currentEvent.currentTarget;
      button.style.position = 'relative';
      button.style.pointerEvents = 'none';
      button.style.overflow = 'hidden';
      const buttonHeight = button.getBoundingClientRect().height;

      const windowWidth = document.body.clientWidth;
      const buttonWidth = button.getBoundingClientRect().width;
      const buttonX = button.getBoundingClientRect().x;
      const buttonSide = button.getBoundingClientRect().x < (windowWidth / 2) ? 'left' : 'right';
      const btnWrapper = document.createElement('div');
      btnWrapper.setAttribute('id', 'tezroPayBtnWrapper');
      btnWrapper.style.position = 'relative';
      btnWrapper.style.width = buttonWidth;

      wrap(button, btnWrapper);

      createSpinnerAnimationStyles();

      button.insertAdjacentHTML('beforeend', externalSpinner(buttonHeight));

      if (!!tezroApi.isMobile()) {
        btnWrapper.insertAdjacentHTML('beforeend', `<div id="tezroPayQRContainer" style="word-break: break-word; cursor: auto; display: flex; flex-direction: column; justify-content: center; align-items: center; border-radius: 10px; padding: 20px; position: absolute; z-index: 1; margin-bottom: 10px; bottom: 100%; min-height: 150px; background-color: #fff;">
          <button onclick='event.stopPropagation(); tezroApi.close();' style='pointer-events: auto; width: 30px; height: 30px; border-radius: 20px; box-shadow: 0px 0px 8px rgba(0,0,0,0.2); position: absolute; z-index: 2; top: -15px; right: -15px; border: none; background-color: #fff; font-size: 14px; font-weight: 700; color: #2C3036'>X</button>
          <div id="tezroPayQRBox" style="display: inline-block;">
            <button style='background: transparent; border: none;' onclick='openLink();' id='tezroPayQRMobileButton'></button>
          </div>
        </div>`);
      } else {
        btnWrapper.insertAdjacentHTML('beforeend', `<div id="tezroPayQRContainer" style="word-break: break-word; cursor: auto; display: flex; flex-direction: column; justify-content: center; align-items: center; border-radius: 10px; padding: 20px; position: absolute; z-index: 1; margin-bottom: 10px; bottom: 100%; min-height: 150px; background-color: #fff;">
          <button onclick='event.stopPropagation(); tezroApi.close();' style='pointer-events: auto; width: 30px; height: 30px; border-radius: 20px; box-shadow: 0px 0px 8px rgba(0,0,0,0.2); position: absolute; z-index: 2; top: -15px; right: -15px; border: none; background-color: #fff; font-size: 14px; font-weight: 700; color: #2C3036'>X</button>
          <div id="tezroPayQRBox" style="display: inline-block;"></div>
        </div>`);
      }

      tezroPayQRContainer = document.getElementById('tezroPayQRContainer');

      tezroPayQRContainer.style.display = 'none';
      tezroPayQRContainer.style.width = buttonWidth < 150 ? '150px' : (buttonWidth - 18) + 'px';
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

    } else {
      if (currentEvent.currentTarget !== event.currentTarget) {
        tezroApi.close();
        tezroApi.runExtButtonSpinner(event);
      }
    }
  },
  stopExtButtonSpinner: function() {
    const spinnerExternalEl = document.getElementById('tezroPayExternalButtonSpinner');
    spinnerExternalEl && spinnerExternalEl.parentNode.removeChild(spinnerExternalEl);
  },
  payInit: function(params) {
    return function(event) {
      initWidget(params, event);
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
  close: function() {
    currentLink = null;
    !!qr && qr.clear();
    isOpen = false;
    currentEvent = null;
    !!tezroPayQRContainer && tezroPayQRContainer.parentNode.removeChild(tezroPayQRContainer);
    tezroApi.stopExtButtonSpinner();
    button.style.pointerEvents = 'auto';
    clearTimeout(reqTimeout);
    clearInterval(reqInterval);
    clearTimeout(clearIntervalTimeout);
  }
}

let initWidget = async function(params, event) {
  currentOrderId = params.orderId;
  
  const branchParams = {
    "orderId": params.orderId,
    "attributes": params.attributes.filter(attr => attr?.value),
    "quantity": params.quantity,
    "name": params.productName,
    "amount": params.amount.toString(),
    "currency": params.currency,
    "confirmAmountUrl": !!params?.confirmAmountUrl ? params.confirmAmountUrl : null,
    "photos": params?.photos ? params.photos : []
  };

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
  
  const data = await fetch(`https://openapi.tezro.com/api/v1/orders/init`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'KeyID': params.keyId,
        'Timestamp': Date.now()
      },
      body: JSON.stringify(branchParams)
    }
  ).then(res => res.json());
  
  if (!!data && data.statusCode === 409) {
    tezroApi.close();
    return;
  }
  
  if (!!data && (!currentLink || currentLink === data.link)) {
    currentLink = data.link;
    tezroPayQRContainer.style.display = 'flex';
    generateQRCode(QRCode, data.link, params.orderId, data.id);
    
    tezroPayQRContainer.insertAdjacentHTML('afterbegin', `<div id="tezroPayTitleBox" style='width: 100%; display: flex; justify-content: center;'>
      <input id='tezropaylink' hidden value='${data.link}'>
      <p style='font-size: 16px; font-weight: 700; color: #2C3036; margin-bottom: 10px;'>${!!tezroApi.isMobile() ? 'Click and Pay' : 'Scan QR'}</p>
    </div>`);

    checkPaymentStatus(data.id, params.keyId, event);
  }

  tezroApi.stopExtButtonSpinner();
}

function generateQRCode(qrCode, qrLink, id,uniqid) {
  let el = !!tezroApi.isMobile() ? document.getElementById('tezroPayQRMobileButton') : document.getElementById('tezroPayQRBox');

  if (!!el) {
    var link = qrLink;
    qr = new qrCode(el, {
      text: link,
      width: 120,
      height: 120
    });
  }
}

function openLink() {
  const link = document.getElementById('tezropaylink').value;

  window.open(link, 'Tezro');
}

function wrap(el, wrapper) {
  el.parentNode.insertBefore(wrapper, el);
  wrapper.appendChild(el);
}

function checkPaymentStatus(orderId, keyId, event) {
  let tezroPayQRBox = document.getElementById('tezroPayQRBox');
  let tezroPayTitleBox = document.getElementById('tezroPayTitleBox');
  
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
  
    const expiresAtDateISO = new Date(statusRes.expiresAt).toISOString();
    const expiresAtDate = new Date(statusRes.expiresAt).toLocaleDateString();
    const expiresAtTime = new Date(statusRes.expiresAt).toLocaleTimeString();

    switch(statusRes.status) {
      case 'address_confirmed': {
        const expiresAt = expiresAtDate + ' ' + expiresAtTime;

        tezroPayQRBox && tezroPayQRBox.parentNode.removeChild(tezroPayQRBox);
        tezroPayTitleBox && tezroPayTitleBox.parentNode.removeChild(tezroPayTitleBox);
        tezroPayQRContainer && tezroPayQRContainer.insertAdjacentHTML('beforeend', addressConfirmedContent(event.currentTarget, expiresAt));
        
        initializeClock('timerCountdown', expiresAtDateISO);

        break;
      }

      case 'order_confirmed': {
        clearInterval(reqInterval);
        clearTimeout(clearIntervalTimeout);
        
        tezroPayQRContainer && tezroPayQRContainer.parentNode.removeChild(tezroPayQRContainer);
        tezroPayQRContainer && tezroPayQRContainer.insertAdjacentHTML('beforeend', paidContent(event.currentTarget));
        
        break;
      }

      case 'order_expired': {
        const currentDDMMYYYY = currentDate.toLocaleDateString();
        const currentHHMMSS = currentDate.toLocaleTimeString();
        const formattedExpiresAtDate = new Date(expiresAtDate + ' ' + expiresAtTime);
        const formattedCurrentDate = new Date(currentDDMMYYYY + ' ' + currentHHMMSS);

        if (formattedExpiresAtDate < formattedCurrentDate) {
          tezroApi.close();
        }
        
        break;
      }

      default: return
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

  return `<div id='tezroPayExternalButtonSpinner' style='position: absolute; 
    top: 0; 
    left: 0; 
    right: 0; 
    bottom: 0; 
    z-index: 1; 
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

let addressConfirmedContent = (currentTarget, expiresAt) => currentTarget === currentEvent.currentTarget && `<div style='display: flex; flex-direction: column; align-items: center;'>
  <h3 style='color: #2c3036; text-align: center;'>Awaiting your payment</h3>
  <p style='margin-top: 7px; text-align: center; color: #828282; font-size: 12px; font-weight: 700;'>Without payment, your order will be cancelled after</p>
  <div style='align-self: stretch; min-height: 40px; display: flex; justify-content: center; align-items: center; background: #2c3036; border-radius: 50px; padding: 5px; margin-top: 10px;'>
    <p id='timerCountdown' style='margin-bottom: 0; font-size: 16px; color: #ffffff; font-weight: 700; text-align: center;'></p>
  </div>
</div>`

let paidContent = (currentTarget) => currentTarget === currentEvent.currentTarget && `<div id={currentTarget} style='display: flex; flex-direction: column; justify-content: center; align-items: center;'>
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

  <p style='color: #2c3036; font-size: 16px; font-weight: 700; margin-top: 20px;'>Paid</p>

  <button onclick='event.stopPropagation(); tezroApi.close();' style='pointer-events: auto; background: linear-gradient(45deg, #2C3036, #575F6B); width: 80px; padding: 5px; font-weight: 700; border-radius: 20px; height: 30px; font-size: 12px; margin-top: 20px; border: none; color: #fff;'>OK</button>
</div>`;