/*
 * Copyright © 2021 Lukas Buchs
 */

class Ranking {

    constructor(routeId) {
        this._routeId = routeId;
        this._container = document.getElementById('ranking');
        this._data = {};
        if (this._container) {
            this.getData().then((data) => {
                this._data = data;
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
        this.buildTable(data.ranking, null, null, null, true);

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
            this.buildTable(data.ranking, null, 'W', null, true);
        }

        this.getUser(data.ranking).forEach((user) => {
            console.log(user);
            let container = document.createElement('div');
            container.id = 'user-' + user;
            container.style.display = 'none';

            this.buildTitle('Alle Zeiten von ' + this.getNameByUser(data.ranking, user), container);
            this.buildTable(data.ranking, null, null, user, true, false, container);

           this._container.appendChild(container);
        });
    }


    buildTable(ranking, filterCategory=null, filterGender=null, filterUser=null, showCategory=false, uniqueUser=true, appendTo=null) {
        let table = document.createElement('table'), place=0;

        // Klassen
        table.classList.add('category_' + (filterCategory || 'all'));
        table.classList.add('gender_' + (filterGender ? filterGender.toLowerCase() : 'all'));

        // Head
        this.addTableHead(table, showCategory, !filterUser);

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
            if (filterUser) {
                if (rank.user !== filterUser) {
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
            let columns = {
                place   : place,
                fullname: filterUser ? null : rank.fullname,
                category: showCategory ? ct : null,
                date    : (new Date(rank.start * 1000)).toLocaleString('de-CH', { year: 'numeric', month: '2-digit', day: '2-digit' }), // Datum
                time    : (new Date(rank.start * 1000)).toLocaleString('de-CH', { hour: 'numeric', minute: 'numeric', second: 'numeric'}), // Zeit
                tp1     : rank.tp1,
                tp2     : rank.tp2,
                goal    : rank.goal
            };

            for (let columnName in columns) {
                let column = columns[columnName];
                if (column !== null) {
                    let cell = document.createElement('td');
                    cell.textContent = column;
                    if (typeof column === 'string' && (column.indexOf('.') !== -1 || column.indexOf(':') !== -1)) {
                        cell.style.textAlign = 'right';
                    }

                    // make link
                    if (columnName === 'fullname') {
                        cell.classList.add('userLink');
                        cell.addEventListener('click', () => {
                            this.goToUser(rank.user);
                        });
                    }

                    row.appendChild(cell);
                }
            };

            table.appendChild(row);
        }

        if (appendTo) {
            appendTo.appendChild(table);
        } else {
            this._container.appendChild(table);
        }
    }

    buildTitle(title, appendTo=null) {
        let el = document.createElement('h2');
        el.textContent = title;

        if (appendTo) {
            appendTo.appendChild(el);
        } else {
            this._container.appendChild(el);
        }
    }


    addTableHead(table, showCategory, showUser) {
        let row = document.createElement('tr');
        table.appendChild(row);

        let columns = [
            'Rang',
            showUser ? 'Name' : null,
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

    goToUser(gtUser) {
        this.getUser(this._data.ranking).forEach((user) => {
            let el = document.getElementById('user-' + user);
            if (el) {
                el.style.display = gtUser === user ? 'initial' : 'none';
            }
        });

        window.location.href = '#user-' + gtUser;
    }

    /**
     * Return all user ids
     * @param {Array} ranking
     * @returns {Array}
     */
    getUser(ranking) {
        let users = [];
        for (let i=0; i<ranking.length; i++) {
            if (ranking[i].user && users.indexOf(ranking[i].user) === -1) {
                users.push(ranking[i].user);
            }
        }
        return users;
    }

    getNameByUser(ranking, user) {
        for (let i=0; i<ranking.length; i++) {
            if (ranking[i].user === user) {
                return ranking[i].fullname;
            }
        }
        return '';
    }
}