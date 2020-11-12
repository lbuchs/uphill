/*
 * Copyright © 2020 Lukas Buchs
 */

class main {

    constructor() {
        this._mainDiv = document.querySelector("main > div");
        this._scannedCode = this._getGetParam('cd');

        this._setContent();
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

    _showForm(data) {
        let formDiv = document.createElement('form'), html='';

        html += '<h1>Willkommen bei der Möntschele Uphill Challange!</h1>';
        html += '<p>Vergleiche deine Zeit mit anderen oder nutze einfach die Stopuhr, um zu sehen, wie lange du für die 676 Höhenmeter benötigst.</p>';
        html += '<h2>So gehts:</h2>';
        html += '<ol>';
        html += '<li>Fülle das Formular aus oder drücke «Bereitmachen» für eine anonyme Teilnahme.</li>';
        html += '<li>Wenn du laufbereit bist, scanne den Start-QR-Code, öffne den Link und laufe los.</li>';
        html += '<li>Nach der Spittelweide, nach dem Tor im Zaun, ist die erste Zwischenzeit. Scanne den QR-Code, öffne den Link und laufe weiter.</li>';
        html += '<li>Beim Bänkli im Möntschelewald ist die zweite Zwischenzeit. Scanne den QR-Code, öffne den Link und laufe weiter.</li>';
        html += '<li>Oben beim Startplatz ist das Ziel. Scanne den QR-Code, um die Zeitmessung abzuschliessen.</li>';
        html += '<li>Guten Flug!</li>';
        html += '</ol>';

        html += '<div class="formEl textEl">';
        html += '<label>Name<input type="text" name="name" placeholder="Anonymer Speedygonzales"></label>';
        html += '</div>';

        html += '<div class="formEl textEl">';
        html += '<label>Email<input type="email" name="email" placeholder="anonymous@pdcs.ch"></label>';
        html += '</div>';

        html += '<div class="formEl radioEl">';
        html += '<p>Geschlecht</p>';
        html += '<label><input type="radio" name="gender" value="W">Sie</label><br>';
        html += '<label><input type="radio" name="gender" value="M">Er</label>';
        html += '</div>';

        html += '<div class="formEl radioEl">';
        html += '<p>Kategorie</p>';
        html += '<label><input type="radio" name="category" value="1" required>Fussgänger</label><br>';
        html += '<label><input type="radio" name="category" value="2" required>Leichtausrüstung</label><br>';
        html += '<label><input type="radio" name="category" value="2" required>Sherpa (> 6kg)</label>';
        html += '</div>';

        html += '<div class="formEl submitEl">';
        html += '<input type="submit" value="Bereitmachen!">';
        html += '</div>';

        formDiv.innerHTML = html;
        this._mainDiv.appendChild(formDiv);
    }
};

