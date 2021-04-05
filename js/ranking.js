/*
 * Copyright © 2021 Lukas Buchs
 */

class Ranking {

    constructor(routeId) {
        this._routeId = routeId;
        this._container = document.getElementById('ranking');
        if (this._container) {
            this.getData().then((data) => {
               this.buildHtml(data);
            }).catch((e) => {
                window.alert(e.message || 'Es ist ein Fehler aufgetreten.');
            });
        }

    }



    async getData() {
        let data = await fetch('https://uphill.pdcs.ch/php/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({action:"getRanking", params:{routeId:this._routeId}})
        });

        data = await data.json();

        if (data && data.success) {
            return data.data;

        } else {
            throw new Error('Das Ranking kann nicht geladen werden.');
        }
    }


    buildHtml(data) {
        this.buildTitle('Gesamtrangliste');
        this.buildTable(data.ranking, null, null, true);

        if (data.categorys.M1 || data.categorys.F1) {
            this.buildTitle('Kategorie Fussgänger');
            this.buildTable(data.ranking, 1);
        }

        if (data.categorys.M2 || data.categorys.F2) {
            this.buildTitle('Kategorie Leichtausrüstung');
            this.buildTable(data.ranking, 2);
        }

        if (data.categorys.M3 || data.categorys.F3) {
            this.buildTitle('Kategorie Sherpa (>8 Kg)');
            this.buildTable(data.ranking, 3);
        }

        if (data.categorys.F1 || data.categorys.F2 || data.categorys.F3) {
            this.buildTitle('Kategorie Damen');
            this.buildTable(data.ranking, null, 'W', true);
        }
    }


    buildTable(ranking, filterCategory=null, filterGender=null, showCategory=false, uniqueUser=true) {
        let table = document.createElement('table'), place=0;

        // Klassen
        table.classList.add('category_' + (filterCategory || 'all'));
        table.classList.add('gender_' + (filterGender ? filterGender.toLowerCase() : 'all'));

        // Head
        this.addTableHead(table, showCategory);

        // user
        let userList = [];

        // Zeilen
        for (let i=0; i<ranking.length; i++) {
            let rank = ranking[i];

            if (filterCategory) {
                if (rank.category !== filterCategory) {
                    continue;
                }
            }
            if (filterGender) {
                if (rank.gender !== filterGender) {
                    continue;
                }
            }

            // Nur die beste Zeit pro User
            if (uniqueUser && userList.indexOf(rank.user) !== -1) {
                continue;
            }
            userList.push(rank.user);

            // Klasse
            let row = document.createElement('tr');
            row.className = 'category_' + rank.category + ' gender_' + rank.gender.toLowerCase();

            // Kat. Bez
            let ct = '';
            switch (rank.category) {
                case 1: ct = 'F'; break;
                case 2: ct = 'L'; break;
                case 3: ct = 'S'; break;
            }

            place++;
            let columns = [
                place,
                rank.fullname,
                showCategory ? ct : null,
                (new Date(rank.start * 1000)).toLocaleString('de-CH', { year: 'numeric', month: '2-digit', day: '2-digit' }), // Datum
                (new Date(rank.start * 1000)).toLocaleString('de-CH', { hour: 'numeric', minute: 'numeric', second: 'numeric'}), // Zeit
                rank.tp1,
                rank.tp2,
                rank.goal
            ];

            columns.forEach((column) => {
                if (column !== null) {
                    let cell = document.createElement('td');
                    cell.textContent = column;
                    if (typeof column === 'string' && (column.indexOf('.') !== -1 || column.indexOf(':') !== -1)) {
                        cell.style.textAlign = 'right';
                    }
                    row.appendChild(cell);
                }
            });

            table.appendChild(row);
        }


        this._container.appendChild(table);
    }

    buildTitle(title) {
        let el = document.createElement('h2');
        el.textContent = title;
        this._container.appendChild(el);
    }


    addTableHead(table, showCategory) {
        let row = document.createElement('tr');
        table.appendChild(row);

        let columns = [
            'Rang',
            'Name',
            showCategory ? 'Kat.' : null,
            'Datum',
            'Startzeit',
            'TP 1',
            'TP 2',
            'Ziel'
        ];

        columns.forEach((column) => {
            if (column !== null) {
                let cell = document.createElement('th');
                cell.textContent = column;
                row.appendChild(cell);
            }
        });
    }
}