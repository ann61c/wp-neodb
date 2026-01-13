// @ts-nocheck
class WP_NEODB {
    constructor() {
        this.ver = "1.0.5";
        this.type = "movie";
        this.status = "done";
        this.finished = false;
        this.paged = 1;
        this.genre_list = [];
        this.genre = [];
        this.subjects = [];
        this._create();
    }

    on(t, e, n) {
        var a = document.querySelectorAll(e);
        a.forEach((item) => {
            item.addEventListener(t, n);
        });
    }

    _addSearchParams(url, params = {}) {
        url = new URL(url);
        let new_url = new URL(
            `${url.origin}${url.pathname}?${new URLSearchParams([
                ...Array.from(url.searchParams.entries()),
                ...Object.entries(params),
            ])}`
        );
        return new_url.href;
    }

    _fetchGenres() {
        document.querySelector(".db--genres").innerHTML = "";
        const url = wpn_base.token
            ? "https://node.wpista.com/v1/outer/genres?token=" + wpn_base.token
            : wpn_base.api + "v1/movie/genres";
        fetch(
            this._addSearchParams(url, {
                type: this.type,
            })
        )
            .then((response) => response.json())
            .then((data) => {
                const t = wpn_base.token ? data.data : data;
                if (t.length) {
                    this.genre_list = t;
                    this._renderGenre();
                }
            });
        return true;
    }

    _statusChange() {
        this.on("click", ".db--typeItem", (t) => {
            const self = t.currentTarget;
            if (self.classList.contains("is-active")) {
                // const index = this.genre.indexOf(self.innerText);
                return;
            }
            document.querySelector(".db--list").innerHTML = "";
            document.querySelector(".lds-ripple").classList.remove("u-hide");
            document
                .querySelector(".db--typeItem.is-active")
                .classList.remove("is-active");
            self.classList.add("is-active");
            this.status = self.dataset.status;
            this.paged = 1;
            this.finished = false;
            this.subjects = [];
            this._fetchData();
            return;
        });
    }

    _handleGenreClick() {
        this.on("click", ".db--genreItem", (t) => {
            const self = t.currentTarget;
            if (self.classList.contains("is-active")) {
                const index = this.genre.indexOf(self.innerText);
                self.classList.remove("is-active");
                this.genre.splice(index, 1);
                this.paged = 1;
                this.finished = false;
                this.subjects = [];
                this._fetchData();
                return;
            }
            document.querySelector(".db--list").innerHTML = "";
            document.querySelector(".lds-ripple").classList.remove("u-hide");

            self.classList.add("is-active");
            this.genre.push(self.innerText);
            this.paged = 1;
            this.finished = false;
            this.subjects = [];
            this._fetchData();
            return;
        });
    }

    _renderGenre() {
        document.querySelector(".db--genres").innerHTML = this.genre_list
            .map((item) => {
                return `<span class="db--genreItem${
                    this.genre_list.includes(item.name) ? " is-active" : ""
                }">${item.name}</span>`;
            })
            .join("");
        this._handleGenreClick();
    }

    _fetchData() {
        const url = wpn_base.token
            ? "https://node.wpista.com/v1/outer/faves"
            : wpn_base.api + "v1/movies";
        fetch(
            this._addSearchParams(url, {
                token: wpn_base.token,
                type: this.type,
                paged: this.paged,
                genre: JSON.stringify(this.genre),
                status: this.status,
            })
        )
            .then((response) => response.json())
            .then((data) => {
                const t = wpn_base.token ? data.data : data;
                // @ts-ignore
                if (t.length) {
                    if (
                        document
                            .querySelector(".db--list")
                            .classList.contains("db--list__card")
                    ) {
                        this.subjects = [...this.subjects, ...t];
                        this._randerDateTemplate();
                    } else {
                        this.subjects = [...this.subjects, ...t];
                        this._randerListTemplate();
                    }
                    document
                        .querySelector(".lds-ripple")
                        .classList.add("u-hide");
                } else {
                    document
                        .querySelector(".db--list")
                        .classList.contains("db--list__card")
                        ? this._randerDateTemplate()
                        : this._randerListTemplate();
                    this.finished = true;
                    document
                        .querySelector(".lds-ripple")
                        .classList.add("u-hide");
                }
            });
    }

    _randerDateTemplate() {
        if (!this.subjects.length)
            return (document.querySelector(
                ".db--list"
            ).innerHTML = `<div class="db--empty"></div>`);
        const result = this.subjects.reduce((result, item) => {
            const date = new Date(item.create_time);
            const year = date.getFullYear();
            const month = date.getMonth() + 1;
            const key = `${year}-${month.toString().padStart(2, "0")}`;
            if (Object.prototype.hasOwnProperty.call(result, key)) {
                result[key].push(item);
            } else {
                result[key] = [item];
            }
            return result;
        }, {});
        let html = ``;
        for (let key in result) {
            const date = key.split("-");
            html += `<div class="db--listBydate"><div class="db--titleDate JiEun"><div class="db--titleDate__day">${date[1]}</div><div class="db--titleDate__month">${date[0]}</div></div><div class="db--dateList__card">`;
            html += result[key]
                .map((movie) => {
                    return `<div class="db--item">${
                        movie.is_top250
                            ? '<span class="top250">Top 250</span>'
                            : ""
                    }<img src="${
                        movie.poster
                    }" referrerpolicy="unsafe-url" class="db--image"><div class="db--score JiEun">${
                        movie.douban_score > 0
                            ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" ><path d="M12 20.1l5.82 3.682c1.066.675 2.37-.322 2.09-1.584l-1.543-6.926 5.146-4.667c.94-.85.435-2.465-.799-2.567l-6.773-.602L13.29.89a1.38 1.38 0 0 0-2.581 0l-2.65 6.53-6.774.602C.052 8.126-.453 9.74.486 10.59l5.147 4.666-1.542 6.926c-.28 1.262 1.023 2.26 2.09 1.585L12 20.099z"></path></svg>' +
                              movie.douban_score
                            : ""
                    }${
                        movie.year > 0 ? " · " + movie.year : ""
                    }</div><div class="db--title"><a href="${
                        this._fixLink(movie.link)
                    }" target="_blank">${movie.name}</a></div>
    
    </div>`;
                })
                .join("");
            html += `</div></div>`;
        }
        document.querySelector(".db--list").innerHTML = html;
    }

    _randerListTemplate() {
        if (!this.subjects.length)
            return (document.querySelector(
                ".db--list"
            ).innerHTML = `<div class="db--empty"></div>`);
        document.querySelector(".db--list").innerHTML = this.subjects
            .map((item) => {
                return `<div class="db--item">${
                    item.is_top250 ? '<span class="top250">Top 250</span>' : ""
                }<img src="${
                    item.poster
                }" referrerpolicy="unsafe-url" class="db--image"><div class="ipc-signpost JiEun">${
                    item.create_time
                }</div><div class="db--score JiEun">${
                    item.douban_score > 0
                        ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" ><path d="M12 20.1l5.82 3.682c1.066.675 2.37-.322 2.09-1.584l-1.543-6.926 5.146-4.667c.94-.85.435-2.465-.799-2.567l-6.773-.602L13.29.89a1.38 1.38 0 0 0-2.581 0l-2.65 6.53-6.774.602C.052 8.126-.453 9.74.486 10.59l5.147 4.666-1.542 6.926c-.28 1.262 1.023 2.26 2.09 1.585L12 20.099z"></path></svg>' +
                          item.douban_score
                        : ""
                }${
                    item.year > 0 ? " · " + item.year : ""
                }</div><div class="db--title"><a href="${
                    this._fixLink(item.link)
                }" target="_blank">${item.name}</a></div>
                </div>
                </div>`;
            })
            .join("");
    }

    _handleScroll() {
        window.addEventListener("scroll", () => {
            var t = window.scrollY || window.pageYOffset;
            // @ts-ignore
            if (
                document.querySelector(".block-more").offsetTop +
                    // @ts-ignore
                    -window.innerHeight <
                    t &&
                document
                    .querySelector(".lds-ripple")
                    .classList.contains("u-hide") &&
                !this.finished
            ) {
                document
                    .querySelector(".lds-ripple")
                    .classList.remove("u-hide");
                this.paged++;
                this._fetchData();
            }
        });
    }

    _handleNavClick() {
        this.on("click", ".db--navItem", (t) => {
            if (t.target.classList.contains("current")) return;
            this.genre = [];
            this.type = t.target.dataset.type;
            if (this.type != "book") {
                this._fetchGenres();
                document
                    .querySelector(".db--genres")
                    .classList.remove("u-hide");
            } else {
                document.querySelector(".db--genres").classList.add("u-hide");
            }
            document.querySelector(".db--list").innerHTML = "";
            document.querySelector(".lds-ripple").classList.remove("u-hide");
            document
                .querySelector(".db--navItem.current")
                .classList.remove("current");
            const self = t.target;
            self.classList.add("current");
            this.paged = 1;
            //this.status = "done";
            this.finished = false;
            this.subjects = [];
            this._fetchData();
        });
    }

    _create() {
        if (document.querySelector(".db--container")) {
            if (document.querySelector(".db--navItem.current")) {
                this.type = document.querySelector(
                    ".db--navItem.current"
                ).dataset.type;
            }
            if (document.querySelector(".db--list").dataset.type)
                this.type = document.querySelector(".db--list").dataset.type;
            if (this.type == "movie") {
                document
                    .querySelector(".db--genres")
                    .classList.remove("u-hide");
            }
            this._fetchGenres();
            this._fetchData();
            this._handleScroll();
            this._handleNavClick();
            this._statusChange();
        }

        if (document.querySelector(".db--collection")) {
            document.querySelectorAll(".db--collection").forEach((item) => {
                this._fetchCollection(item);
            });
        }
    }

    _fixLink(link) {
        if (!link) return "";
        if (link.startsWith("/") && wpn_base.neodb_url) {
            // Remove trailing slash from base url if present, and leading slash from link
            const baseUrl = wpn_base.neodb_url.replace(/\/+$/, "");
            const path = link.replace(/^\/+/, "");
            return baseUrl + "/" + path;
        }
        return link;
    }

    _fetchCollection(item) {
        const type = item.dataset.style ? item.dataset.style : "card";
        const url = wpn_base.token
            ? "https://node.wpista.com/v1/outer/faves?token=" + wpn_base.token
            : wpn_base.api + "v1/movies";
        fetch(
            this._addSearchParams(url, {
                type: this.type,
                paged: 1,
                start_time: item.dataset.start,
                end_time: item.dataset.end,
                status: item.dataset.status,
            })
        )
            .then((response) => response.json())
            .then((data) => {
                const t = wpn_base.token ? data.data : data;
                // @ts-ignore
                if (t.length) {
                    if (type == "card") {
                        item.innerHTML += t
                            .map((movie) => {
                                // Parse metadata fields (stored as JSON strings)
                                const director = movie.director ? JSON.parse(movie.director) : [];
                                const actor = movie.actor ? JSON.parse(movie.actor) : [];
                                const externalResources = movie.external_resources ? JSON.parse(movie.external_resources) : [];
                                
                                // Build external links badges (inline)
                                let badgesHtml = '';
                                externalResources.forEach(res => {
                                    if (!res.url) return;
                                    let name = '', cls = '';
                                    if (res.url.includes('douban.com')) { name = '豆瓣'; cls = 'douban'; }
                                    else if (res.url.includes('themoviedb.org')) { name = 'TMDB'; cls = 'tmdb'; }
                                    else if (res.url.includes('imdb.com')) { name = 'IMDb'; cls = 'imdb'; }
                                    else if (res.url.includes('wikidata.org')) { name = '维基数据'; cls = 'wikidata'; }
                                    else if (res.url.includes('spotify.com')) { name = 'Spotify'; cls = 'spotify'; }
                                    else if (res.url.includes('igdb.com')) { name = 'IGDB'; cls = 'igdb'; }
                                    else if (res.url.includes('steampowered.com') || res.url.includes('steamcommunity.com')) { name = 'Steam'; cls = 'steam'; }
                                    if (name) badgesHtml += ` <a href="${res.url}" class="${cls}" target="_blank" rel="noopener noreferrer">${name}</a>`;
                                });

                                // Type
                                const typeMap = {movie: '影视', book: '书籍', music: '音乐', game: '游戏', drama: '戏剧', tv: '剧集', podcast: '播客'};
                                const typePart = movie.type ? ` <span class="doulist-category">[${typeMap[movie.type] || movie.type}]</span> ` : '';
                                
                                // Build Header
                                const headerHtml = `<div class="doulist-title-header">
                                    <a href="${this._fixLink(movie.link)}" class="doulist-title cute" target="_blank" rel="external nofollow">${movie.name}</a>
                                    ${movie.year ? ` <span class="doulist-year">(${movie.year})</span>` : ''}
                                    ${typePart}
                                    <span class="site-list">${badgesHtml}</span>
                                </div>`;
                                
                                // Build Subtitle
                                const subtitleHtml = movie.orig_title && movie.orig_title !== movie.name 
                                    ? `<div class="doulist-subtitle">${movie.orig_title}</div>` : '';

                                // Meta Line 1: Rating / Other Titles / PubDate
                                const meta1Items = [];
                                if (movie.douban_score > 0) {
                                    meta1Items.push(`<span class="rating-score">${movie.douban_score}</span>`);
                                }
                                if (movie.orig_title && movie.orig_title !== movie.name) {
                                    meta1Items.push(`其它标题: ${movie.orig_title}`);
                                }
                                if (movie.pubdate && movie.pubdate !== movie.year) {
                                    meta1Items.push(movie.pubdate);
                                }
                                const meta1Html = meta1Items.length > 0 ? `<div class="doulist-meta-line">${meta1Items.join(' / ')}</div>` : '';

                                // Meta Line 2: Genres / Director / Actor / etc.
                                const meta2Items = [];
                                
                                // Genres
                                if (movie.genres) {
                                    const genres = movie.genres.split(',').map(g => g.trim()).filter(g => g !== movie.year && !g.endsWith('s'));
                                    if (genres.length > 0) {
                                        meta2Items.push('类型: ' + genres.slice(0, 3).join(' / '));
                                    }
                                }

                                const directorLabel = movie.type === 'game' ? '开发者: ' : '导演: ';
                                const actorLabel = movie.type === 'game' ? '平台: ' : '演员: ';
                                
                                if (director.length > 0) meta2Items.push(directorLabel + director.slice(0, 2).join('·'));
                                if (actor.length > 0) meta2Items.push(actorLabel + actor.slice(0, 4).join(' / '));
                                
                                const meta2Html = meta2Items.length > 0 ? `<div class="doulist-meta-line">${meta2Items.join(' / ')}</div>` : '';

                                return `<div class="doulist-item">
                            <div class="doulist-subject">
                            <div class="db--viewTime JiEun">Marked ${movie.create_time}</div>
                            <div class="doulist-post"><img referrerpolicy="unsafe-url" src="${movie.poster}"></div>
                            <div class="doulist-content">
                                ${headerHtml}
                                ${subtitleHtml}
                                ${meta1Html}
                                ${meta2Html}
                                <div class="abstract">${movie.remark || movie.card_subtitle || ''}</div>
                                ${movie.genres ? `<div class="tag-list">${movie.genres.split(',').map(g => `<span><a href="#">${g.trim()}</a></span>`).join('')}</div>` : ''}
                            </div></div></div>`;
                            })
                            .join("");
                    } else {
                        const result = t.reduce((result, item) => {
                            if (
                                Object.prototype.hasOwnProperty.call(
                                    result,
                                    item.create_time
                                )
                            ) {
                                result[item.create_time].push(item);
                            } else {
                                result[item.create_time] = [item];
                            }
                            return result;
                        }, {});
                        let html = ``;
                        for (let key in result) {
                            html += `<div class="db--date">${key}</div><div class="db--dateList">`;
                            html += result[key]
                                .map((movie) => {
                                    return `<div class="db--card__list"">
                                    <img referrerpolicy="unsafe-url" src="${
                                        movie.poster
                                    }">
                                    <div>
                                    <div class="title"><a href="${
                                        this._fixLink(movie.link)
                                    }" class="cute" target="_blank" rel="external nofollow">${
                                        movie.name
                                    }</a></div>
                                    <div class="rating"><span class="allstardark"><span class="allstarlight" style="width:75%"></span></span><span class="rating_nums">${
                                        movie.douban_score
                                    }</span></div>
                                    ${movie.remark || movie.card_subtitle}
                                    </div>
                                    </div>`;
                                })
                                .join("");
                            html += `</div>`;
                        }
                        item.innerHTML = html;
                    }
                }
            });
    }
}

new WP_NEODB();
