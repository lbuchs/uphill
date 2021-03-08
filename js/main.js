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
                
            } else {
                // Button für Scanner einblenden
                let btn = document.createElement('button');
                btn.value = 'QR-Code scannen';
                btn.addEventListener('click', async () => {
                   this._getQrScan().then((scannerData) => {

                   });
                });
            }

            if (commands.action === 'showTime') {
                this._showTime(commands.html);
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
                    window.alert('Deine Handy-Zeit weicht ' + Math.round(diff/1000) + ' Sekunden von der aktuellen Zeit ab. Die Anzeige kann ungenau sein.');
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
        let email = data.email ? data.email : '';

        html += '<h1>Willkommen bei der Möntschele Uphill Challange!</h1>';
        html += '<p>Vergleiche deine Zeit mit anderen oder nutze einfach die Stopuhr, um zu sehen, wie lange du für die 676 Höhenmeter benötigst.</p>';

        html += '<div class="slider">';
        html += '<h2>So gehts</h2>';
        html += '<div class="plusSign">+</div>';
        html += '<ol>';
        html += '<li>Fülle das Formular aus und drücke «Bereitmachen».</li>';
        html += '<li>Wenn du laufbereit bist, scanne den Start-QR-Code, öffne den Link und laufe los.</li>';
        html += '<li>Nach der Spittelweide, nach dem Tor im Zaun, ist die erste Zwischenzeit. Scanne den QR-Code, öffne den Link und laufe weiter.</li>';
        html += '<li>Beim Bänkli im Möntschelewald ist die zweite Zwischenzeit. Scanne den QR-Code, öffne den Link und laufe weiter.</li>';
        html += '<li>Oben beim Startplatz ist das Ziel. Scanne den QR-Code, um die Zeitmessung abzuschliessen.</li>';
        html += '<li>Guten Flug!</li>';
        html += '</ol>';
        html += '</div>';

        html += '<div class="formEl textEl">';
        html += '<label for="fld_name">Vorname</label><input type="text" name="name" placeholder="Vorname" id="fld_name" value="' + (data.name || '') + '">';
        html += '</div>';
        
        html += '<div class="formEl textEl">';
        html += '<label for="fld_familyname">Nachname</label><input type="text" name="familyname" placeholder="Nachname" id="fld_familyname" value="' + (data.familyname || '') + '">';
        html += '</div>';

        html += '<div class="formEl textEl">';
        html += '<label for="fld_email">Email</label><input type="email" name="email" placeholder="Email-Adresse" id="fld_email" value="' + (data.email || '') + '">';
        html += '</div>';

        html += '<div class="formEl radioEl">';
        html += '<p>Geschlecht</p>';
        html += '<label><input type="radio" name="gender" value="W" required ' + (data.gender==='W' ? 'checked' : '') + '>Sie</label><br>';
        html += '<label><input type="radio" name="gender" value="M" required ' + (data.gender==='M' ? 'checked' : '') + '>Er</label>';
        html += '</div>';

        html += '<div class="formEl radioEl">';
        html += '<p>Ausrüstung</p>';
        html += '<label><input type="radio" name="category" value="1" required ' + (data.category===1 ? 'checked' : '') + '>Ohne Gleitschirm</label><br>';
        html += '<label><input type="radio" name="category" value="2" required ' + (data.category===2 ? 'checked' : '') + '>Leichtausrüstung</label><br>';
        html += '<label><input type="radio" name="category" value="3" required ' + (data.category===3 ? 'checked' : '') + '>Sherpa (> 8kg)</label>';
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
                canvas.width = Math.max(document.body.clientHeight * 0.5, document.body.clientWidth * 0.9);
                canvas.height = canvas.width;
                canvas.classList.add('qrScanner');
                this._mainDiv.appendChild(canvas);
                
                this._mainDiv.removeChild(canvas);
                
                // TODO: QR-Library
                resolve({code: 'sbasjdlsjl', img: 'dhkasdjhsk'});
                
            } catch (er) {
                reject(er);
            }
        });
        
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

    _showReadyScreen() {
        this._clearElement(this._mainDiv);

        let readyDiv = document.createElement('div'), html='';
        readyDiv.className = 'ready';

        html += '<h1>Lets go!</h1>';
        html += '<p>Du kannst deinen Lauf beginnen.</p>';

        readyDiv.innerHTML = html;
        this._mainDiv.appendChild(readyDiv);
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

