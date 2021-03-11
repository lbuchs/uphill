/*
 * Copyright © 2020 Lukas Buchs
 */

class main {

    constructor() {
        this._mainDiv = document.querySelector("main > div");
        this._timerDiv = document.querySelector("div.timer");
        this._scannedCode = this._getGetParam('cd');
        this._startTime = null;
        this._finishTime = null;

        this._clockElements = {
            hour: document.querySelector("body div.timer > span.hour"),
            minute: document.querySelector("body div.timer > span.minute"),
            second: document.querySelector("body div.timer > span.second")
        };

        this._setContent();
        this._updateTimer();

    }


    async _setContent() {
        this._clearElement(this._mainDiv);
        this._addLoader();

        try {
            let commands = await this._queryApi('getContent', {code: this._scannedCode});
            this._clearElement(this._mainDiv);

            if (commands.action === 'showForm') {
                this._showForm(commands.data);

            }

            if (commands.action === 'showTime') {

                // Button für Scanner einblenden
                if (!commands.data.finishTime) {
                    let btn = document.createElement('button');
                    btn.className = 'qr-code-scan';
                    btn.innerHTML = 'QR-Code scannen';
                    btn.addEventListener('click', () => {
                        this._onQrButtonClick();
                    });
                    this._mainDiv.appendChild(btn);

                } else {

                    // Button neu starten
                    let btn = document.createElement('button');
                    btn.className = 'qr-code-scan';
                    btn.innerHTML = 'Neu starten';
                    btn.addEventListener('click', () => {
                        this._clearElement(this._mainDiv);
                        this._addLoader();
                        this._queryApi('endCurrentRun').then(() => {
                            this._setContent();
                        });
                    });
                    this._mainDiv.appendChild(btn);
                }

                this._showTime(commands.html);


                // Abbrechen
                if (!commands.data.finishTime) {
                    let btn = document.createElement('button');
                    btn.className = 'qr-code-scan';
                    btn.innerHTML = 'Lauf abbrechen';
                    btn.style.marginTop = '20px';
                    btn.addEventListener('click', () => {
                        if (window.confirm('Möchtest du wirklich abbrechen?')) {
                            this._clearElement(this._mainDiv);
                            this._addLoader();
                            this._queryApi('endCurrentRun').then(() => {
                                this._setContent();
                            });
                        }
                    });
                    this._mainDiv.appendChild(btn);

                }

            }

            if (commands.data && commands.data.startTime !== undefined) {
                if (commands.data.startTime === null) {
                    this._startTime = null;
                } else {
                    this._startTime = new Date(commands.data.startTime * 1000);
                }
            }

            if (commands.data && commands.data.finishTime !== undefined) {
                if (commands.data.finishTime === null) {
                    this._finishTime = null;
                } else {
                    this._finishTime = new Date(commands.data.finishTime * 1000);
                }
            }

            if (commands.currentTime) {
                let diff = Date.now() - commands.currentTime;
                if (diff > (30*1000)) {
                    window.alert('Deine Handy-Zeit weicht ' + Math.round(diff/1000) + ' Sekunden von der aktuellen Zeit ab. Die Live-Anzeige kann ungenau sein, die Endzeit ist korrekt.');
                }
            }

        } catch (e) {
            this._clearElement(this._mainDiv);
            this._mainDiv.innerHTML = '<p><b>Fehler</b><br>' + e.message + '</p>';
        }
    }


    async _queryApi(action, params=null) {
        const rep = await window.fetch('php/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({action: action, params: params})
        });

        const json = await rep.json();

        if (json.success !== true) {
             throw new Error(json.msg);

        }
        return json.data;
    }

    _getGetParam(name) {
        let vars = window.location.search.substring(1).split("&");

        for (let i=0;i<vars.length;i++) {
            let pair = vars[i].split("=");
            if (pair[0] === name) {
                return decodeURIComponent(pair[1]);
            }
        }

        return undefined;
    }

    _clearElement(domElement) {
        while (domElement.firstChild) {
            domElement.removeChild(domElement.firstChild);
        }
    }


    _addLoader() {
        let el = document.createElement('div');
        el.className = 'loader';
        this._mainDiv.appendChild(el);
    }

    _updateTimer() {
        let hours=0, minutes=0, seconds=0, milliseconds=0;
        if (this._startTime instanceof Date) {
            if (this._finishTime === null) {
                milliseconds = Date.now() - this._startTime.getTime();

            } else {
                milliseconds = this._finishTime.getTime() - this._startTime.getTime();
            }

            hours = Math.floor(milliseconds / 1000 / 3600);
            milliseconds -= hours * 1000 * 3600;

            minutes = Math.floor(milliseconds / 1000 / 60);
            milliseconds -= minutes * 1000 * 60;

            seconds = Math.floor(milliseconds / 1000);
            milliseconds -= seconds * 1000;
        }

        this._clearElement(this._clockElements.hour);
        this._clearElement(this._clockElements.minute);
        this._clearElement(this._clockElements.second);

        this._clockElements.hour.appendChild(document.createTextNode(this._strPad(hours, 2, '0')));
        this._clockElements.minute.appendChild(document.createTextNode(this._strPad(minutes, 2, '0')));
        this._clockElements.second.appendChild(document.createTextNode(this._strPad(seconds, 2, '0')));

        // jede sekunde neu zeichnen
        window.setTimeout(() => { window.requestAnimationFrame(() => {this._updateTimer(); }); }, 1000 - milliseconds);
    }

    _showForm(data) {
        let formEl = document.createElement('form'), html='';

        let name = data.name ? data.name : '';
        let familyname = data.familyname ? data.familyname : '';
        let email = data.email ? data.email : '';

        html += '<h1>Willkommen bei der Möntschele Uphill Challange!</h1>';
        html += '<p>Vergleiche deine Zeit mit anderen oder nutze einfach die Stopuhr, um zu sehen, wie lange du für die 676 Höhenmeter benötigst.</p>';

        html += '<div class="slider">';
        html += '<h2>So gehts</h2>';
        html += '<div class="plusSign">+</div>';
        html += '<ol>';
        html += '<li>Fülle das Formular aus und drücke «Bereitmachen».</li>';
        html += '<li>Wenn du laufbereit bist, scanne den Start-QR-Code und laufe los.</li>';
        html += '<li>Nach der Spittelweide, nach dem Tor im Zaun, ist die erste Zwischenzeit. Scanne den QR-Code und laufe weiter.</li>';
        html += '<li>Beim Bänkli im Möntschelewald ist die zweite Zwischenzeit. Scanne den QR-Code und laufe weiter.</li>';
        html += '<li>Oben beim Startplatz ist das Ziel. Scanne den QR-Code, um die Zeitmessung abzuschliessen.</li>';
        html += '<li>Guten Flug!</li>';
        html += '</ol>';
        html += '</div>';

        html += '<div class="formEl textEl">';
        html += '<label for="fld_name">Vorname</label><input type="text" name="name" placeholder="Vorname" id="fld_name" value="' + name + '">';
        html += '</div>';

        html += '<div class="formEl textEl">';
        html += '<label for="fld_familyname">Nachname</label><input type="text" name="familyname" placeholder="Nachname" id="fld_familyname" value="' + familyname + '">';
        html += '</div>';

        html += '<div class="formEl textEl">';
        html += '<label for="fld_email">Email</label><input type="email" name="email" placeholder="Email-Adresse" id="fld_email" value="' + email + '">';
        html += '</div>';

        html += '<div class="formEl radioEl">';
        html += '<p>Geschlecht</p>';
        html += '<label><input type="radio" name="gender" value="W" required ' + (data.gender==='W' ? 'checked' : '') + '>Sie</label><br>';
        html += '<label><input type="radio" name="gender" value="M" required ' + (data.gender==='M' ? 'checked' : '') + '>Er</label>';
        html += '</div>';

        html += '<div class="formEl radioEl">';
        html += '<p>Ausrüstung</p>';
        html += '<label><input type="radio" name="category" value="1" required>Ohne Gleitschirm</label><br>';
        html += '<label><input type="radio" name="category" value="2" required>Leichtausrüstung</label><br>';
        html += '<label><input type="radio" name="category" value="3" required>Sherpa (> 8kg)</label>';
        html += '</div>';

        html += '<div class="formEl submitEl">';
        html += '<input type="submit" value="Bereitmachen!">';
        html += '</div>';

        formEl.innerHTML = html;
        this._mainDiv.appendChild(formEl);

        formEl.addEventListener('submit', this._onFormSubmit.bind(this));
    }

    _showTime(html) {
        let timeEl = document.createElement('div');
        timeEl.className = 'times';
        timeEl.innerHTML = html;
        this._mainDiv.appendChild(timeEl);
    }

    _getQrScan() {
        return new Promise((resolve, reject) => {
            try {
                this._clearElement(this._mainDiv);
                let canvas = document.createElement('canvas');
                canvas.width = Math.min(document.body.clientHeight * 0.5, document.body.clientWidth * 0.9);
                canvas.height = canvas.width;
                canvas.classList.add('qrScanner');
                this._mainDiv.appendChild(canvas);

                this._canvasCtx = canvas.getContext('2d');
                this._webcamMsg = 'Browser nicht unterstützt.';
                this._webcamVideo = null;

                if (navigator.mediaDevices) {
                    this._webcamMsg = 'Warte auf Freigabe...';

                    navigator.mediaDevices.getUserMedia({ audio: false, video: true }).then((stream) => {
                        this._webcamStream = stream;
                        this._webcamVideo = document.createElement('video');
                        this._webcamVideo.srcObject = stream;
                        this._webcamVideo.setAttribute("playsinline", true); // required to tell iOS safari we don't want fullscreen
                        this._webcamVideo.play();

                    }).catch((streamErr) => {
                        switch (streamErr.name) {
                            case 'NotFoundError': this._webcamMsg = 'Keine Kamera'; break;
                            case 'NotReadableError': this._webcamMsg = 'Kamerafehler'; break;
                            case 'OverconstrainedError': this._webcamMsg = 'Kein Kamerazugriff'; break;
                            case 'SecurityError': this._webcamMsg = 'Kein Kamerazugriff'; break;
                            case 'TypeError': this._webcamMsg = 'Kein Kamerazugriff'; break;
                            default: this._webcamMsg = 'Kein Kamerazugriff'; break;
                        }

                        if (streamErr.message) {
                            this._webcamMsg += ' (' + streamErr.message + ')';
                        }

                        window.setTimeout(() => {
                           reject(new Error(this._webcamMsg));
                        }, 4000);
                    });
                }

                this._drawCanvas();
                this._detectQr(resolve);

            } catch (er) {
                reject(er);
            }
        });

    }

    _drawCanvas() {
        if (this._webcamVideo) {

            // beendet?
            if (this._webcamVideo.ended || this._webcamVideo.error) {
                return;
            }

            let vW = this._webcamVideo.videoWidth, vH = this._webcamVideo.videoHeight, vM = Math.min(vW, vH);
            let cW = this._canvasCtx.canvas.width, cH = this._canvasCtx.canvas.height;

            this._canvasCtx.drawImage(this._webcamVideo, (vW-vM)/2, (vH-vM)/2, vM, vM, 0, 0, cW, cH);


        } else {
            this._canvasCtx.fillStyle =  '#e8e8e8';
            this._canvasCtx.fillRect(0,0,this._canvasCtx.canvas.width, this._canvasCtx.canvas.height);

            this._canvasCtx.fillStyle =  'white';
            for (var x=0; x<this._canvasCtx.canvas.width; x+=3) {
                for (var y=0; y<this._canvasCtx.canvas.height; y+=3) {
                    if (Math.random() > 0.5) {
                        this._canvasCtx.fillRect(x,y,3,3);
                    }
                }
            }

            if (this._webcamMsg) {
                this._canvasCtx.fillStyle =  'black';
                this._canvasCtx.font = '15px sans-serif';
                let tw = this._canvasCtx.measureText(this._webcamMsg);
                let tx = (this._canvasCtx.canvas.width - tw.width) / 2;
                let ty = this._canvasCtx.canvas.height / 2;

                this._canvasCtx.fillText(this._webcamMsg, tx, ty);
            }

        }

        window.requestAnimationFrame(()=> {
            this._drawCanvas();
        });
    }

    _detectQr(resolveFn) {
        let stop = false;
        if (this._webcamVideo && !this._webcamVideo.ended && !this._webcamVideo.error) {
            let imageData = this._canvasCtx.getImageData(0, 0, this._canvasCtx.canvas.width, this._canvasCtx.canvas.height);
            let code = jsQR(imageData.data, imageData.width, imageData.height, {
              inversionAttempts: "dontInvert",
            });
            if (code && code.data) {
                stop = true;

                // webcam stoppen
                let vt = this._webcamVideo.srcObject.getVideoTracks();
                if (vt) {
                    for(let i=0; i<vt.length; i++) {
                        vt[i].stop();
                    }
                }
                this._webcamVideo = null;

                resolveFn({code: code.data, img: this._canvasCtx.canvas.toDataURL('image/jpeg')});
            }
        }

        if (!stop) {
            window.requestAnimationFrame(()=> {
                this._detectQr(resolveFn);
            });
        }
    }


    async _onFormSubmit(e) {
        e.preventDefault();
        let formData = new FormData(e.target), formPacket={};

        for (var [key, value] of formData.entries()) {
            formPacket[key] = value.toString().trim();
            if (key === 'category') {
                formPacket[key] = parseInt(formPacket[key]);
            }
        }

        this._queryApi('saveForm', {formPacket: formPacket}).then((r) => {
            this._showReadyScreen();
        });
    }

    async _onQrButtonClick() {
        this._getQrScan().then((scannerData) => {
            this._clearElement(this._mainDiv);
            this._addLoader();

            this._queryApi('saveQrScan', scannerData).then((res) => {

                this._clearElement(this._mainDiv);

                if (!res.saved) {
                   this._showMsgScreen('X', 'Code bereits gescannt', '#F77920');
                } else if (res.isStart) {
                   this._showMsgScreen('Go!', 'Laufe los!', '#00B009');
                } else if (res.isEnd) {
                   this._showMsgScreen('Super!', 'Du bist im Ziel.', '#00B009');
                } else {
                   this._showMsgScreen('OK!', 'Weiter gehts!', '#00B009');
                }

                window.setTimeout(() => {
                    this._clearElement(this._mainDiv);
                    this._setContent();
                }, 3000);


            }).catch((e) => {
                if (e && e.message) {
                    window.alert(e.message);
                }
               this._setContent();
            });

        }).catch((e) => {
            if (e && e.message) {
                window.alert(e.message);
            }
           this._setContent();
        });
    }


    _showMsgScreen(title, msg, color) {
        let readyDiv = document.createElement('div'), html='';
        readyDiv.className = 'scan-result';
        readyDiv.style.backgroundColor = color;
        html += '<div>';
        html += '<h1>' + title + '</h1>';
        html += '<p>' + msg + '</p>';
        html += '<p>&nbsp;</p>';
        html += '</div>';

        readyDiv.innerHTML = html;
        this._mainDiv.appendChild(readyDiv);
    }


    _showReadyScreen() {
        this._clearElement(this._mainDiv);

        let readyDiv = document.createElement('div'), html='';
        readyDiv.className = 'ready';

        html += '<h1>Lets go!</h1>';
        html += '<p>Scanne den ersten QR-Code und laufe los.</p>';
        html += '<p>&nbsp;</p>';

        readyDiv.innerHTML = html;
        this._mainDiv.appendChild(readyDiv);

        let btn = document.createElement('button');
        btn.className = 'qr-code-scan';
        btn.innerHTML = 'QR-Code scannen';
        btn.addEventListener('click', () => {
            this._onQrButtonClick();
        });
        this._mainDiv.appendChild(btn);
    }

    _strPad(input, pad_length, pad_string, pad_type='left') {
        input = input.toString();
        while (input.length < pad_length && pad_string.toString().length > 0) {
            if (pad_type === 'left') {
                input = pad_string.toString() + input;

            } else if (pad_type === 'right') {
                input += pad_string.toString();
            } else {
                throw new Error('invalid pad_type');
            }
        }

        return input;
    }
};

