import SpinnerLoader from './components/SpinnerLoader.js'
import Step from './components/Step.js'
import Translations from './components/Translations.js'

let rootsElements = ['.pdf-handler-container'];
let isVueJS3 = (typeof Vue.createApp == "function");

let appParams = {
    components: { Translations, Step, SpinnerLoader },
    data: function() {
        return {
            buttonAction: null,
            buttonType: 'primary',
            buttonTitle: '',
            downloadFileName: '',
            downloadLink: '',
            hash: '',
            isAdmin: null,
            message: '',
            messageType: 'danger',
            pageTag:'',
            pdfDownloaded: 0,
            pdfServiceContacted: 0, // 0 = waiting, 1 = running, 2 = success, 3 = error
            sourceUrl: '',
            translations: {},
            urlOfPdfServiceGet: 0,
            urls: {
                external:'',
                local: ''
            },
            urlsChecked: 0,
        };
    },
    computed: {
    },
    methods: {
        appendSourceUrl: function(serverUrl){
            let firstDelimiter = (serverUrl.includes('?')) ? '&' : '?';
            return `${serverUrl}${firstDelimiter}urlPageTag=${encodeURI(this.pageTag)}&url=${encodeURI(this.sourceUrl)}&hash=${this.hash}`;
        },
        checkUrls: async function (urls){
            this.urlsChecked = 1;
            if (this.urlOfPdfServiceGet != 2){
                this.urlsChecked = 3;
                return Promise.reject('\'this.urlOfPdfServiceGet\ should be set to \'2\' when checking urls !');
            }
            if (this.urls.local.length == 0 && this.urls.external.length == 0){
                this.urlsChecked = 3;
                return Promise.reject(this.t('errorforurls',{link:this.renderHelpUrlButton()}));
            }
            try {
                if (this.urls.local.length > 0){
                    let localUrl = new URL(this.urls.local);
                }
                if (this.urls.external.length > 0){
                    let externalUrl = new URL(this.urls.external);
                }
            } catch (error) {
                this.urlsChecked = 3;
                return Promise.reject(`urls are not empty or urls : ${JSON.stringify(urls)}, error : ${error.toString()} !`);
            }
            this.urlsChecked = 2;
            return urls;
        },
        contactServer: async function(url){
            return fetch(url,{referrer:wiki.url(this.pageTag)})
                .then((response)=>{
                    if (!response.ok && [401,403,404].includes(response.status)){
                        return Promise.reject(`${response.status} => ${response.statusText}`);
                    }
                    return response;
                })
                .catch((error)=>{
                    let errorMessage = this.t('errorforexternaleurlcheck',{
                        extUrl: url,
                        helpLink: this.renderHelpUrlButton(),
                        error: error.toString()
                    });
                    if (error instanceof TypeError && error.message.match(/^NetworkError/)){
                        this.buttonAction = this.openDefaultLink;
                        this.buttonTitle = this.t('opendefaultlink');
                        if (this.isAdmin){
                            this.messageType = 'warning';
                            this.message = errorMessage;
                        } else {
                            this.messageType = 'info';
                            this.message = this.t('errorreloading')
                            this.openDefaultLink();
                        }
                        return Promise.reject(new Error('===Do Nothing==='));
                    }
                    return Promise.reject(errorMessage);
                })
                .then((response)=>{
                    if (!response.ok && response.status != 503){
                        return Promise.reject(`Not possible to get url because response code is not right : '${response.status}' => '${response.statusText}'`)
                    }
                    let headers = response.headers;
                    if (!headers.has('Content-Type')){
                        return Promise.reject(`Bad format of response to url '${url}' : should contain 'Content-Type' header`)
                    } else {
                        let contentType = headers.get('Content-Type');
                        if (response.status == 503 ){
                            if (contentType.match(/^application\/json.*/)){
                                return this.manageJsonError({url,response,contentType});
                            } else {
                                return this.renderErrorJsonFormat({url,response,contentType});
                            }
                        }
                        if (contentType.match(/^text\/html.*/)){
                            return this.manageHtmlReturn(response,url);
                        } else if ((!this.isOldFormatUrl(url) || response.redirected) && contentType.match(/^application\/json.*/)) {
                            return this.manageJsonError({url,response,contentType});
                        } else if (!contentType.match(/^(?:application\/octet-stream|application\/download|application\/pdf).*/)) {
                            return Promise.reject(`Bad format of response to url '${url}' : 'Content-Type' header should contain 'application/pdf' : ${contentType}`);
                        }
                    }
                    return response;
                });
        },
        clickPdfLink: function(){
            this.$nextTick(()=>{
                let element = document.querySelector(`[download="${this.downloadFileName}"]`);
                if (element && element != undefined){
                    element.click();
                }
            });
        },
        createPdfLink: function(blob,fileName){
            this.downloadFileName = fileName;
            this.downloadLink = URL.createObjectURL(new File([blob],fileName,{type:'application/octet-stream'}));
        },
        getPdf: async function(urls = {local:'',external:''}){
            this.pdfServiceContacted = 1;
            let urlToContact = urls.local.length > 0 ? urls.local : urls.external;
            let fullUrl = this.appendSourceUrl(urlToContact);
            return await this.contactServer(fullUrl)
                .then((response)=>{
                    this.pdfServiceContacted = 2;
                    this.pdfDownloaded = 1;
                    let fileName = `${this.pageTag}-${this.hash}.pdf`;
                    return response.blob().then((blob)=>{
                        this.pdfDownloaded = 2;
                        return {blob,fileName};
                    });
                })
                .catch((error)=>{
                    if (this.pdfServiceContacted < 2){
                        this.pdfServiceContacted = 3;
                    }
                    if (this.pdfDownloaded > 0){
                        this.pdfDownloaded = 3;
                    }
                    throw error;
                });
        },
        getUrlOfPdfService: async function(baseEl){
            this.urls = JSON.parse(baseEl.dataset.urls);
            let urls = this.urls;
            this.urlOfPdfServiceGet = 1;
            if (typeof urls == "object" && 
                    'local' in urls && 
                    'external' in urls && 
                    typeof urls.local == 'string' && 
                    typeof urls.external == 'string'){
                this.urlOfPdfServiceGet = 2;
                return this.urls;
            } else {
                this.urlOfPdfServiceGet = 3;
                return Promise.reject(`Not possible to get url of pdf service because data-urls doest not contain right 'local' and 'external' : '${JSON.stringify(this.urls)}'`);
            }
        },
        isOldFormatUrl: function(url){
            return !url.match(/api\/pdf\//);
        },
        manageButton: function(event){
            if (typeof this.buttonAction == "function"){
                this.buttonAction(event);
            }
        },
        manageHtmlReturn: async function(response,url){
            let html = await response.text();
            if (html.match(/"htmltopdf_path"/) && html.match(/"htmltopdf_service_url"/)){
                return Promise.reject(this.t('errorforexternaleurlnotconfigured',{
                    extUrl: url,
                    helpLink: this.renderHelpUrlButton()
                }));
            }
            if (html.match(/PUBLICATION_DOMAIN_NOT_AUTORIZED/)){
                return this.renderDomainNotAuthorized(url);
            }
            return Promise.reject('html return');
        },
        manageJsonError: async function ({url,response,contentType}){
            let jsonContent = await response.json();
            if ('error' in jsonContent && jsonContent.error === true){
                if ('cause' in jsonContent && typeof jsonContent.cause == 'object'){
                    if ('canExecChromium' in jsonContent.cause && jsonContent.cause.canExecChromium === false){
                        return Promise.reject(this.t('errorforexternalwithoutchromium',{url:url,helpLink:this.renderHelpUrlButton()}));
                    }
                    if ('domainAuthorized' in jsonContent.cause && jsonContent.cause.domainAuthorized === false){
                        return this.renderDomainNotAuthorized(url);
                    }
                }
                return Promise.reject(`Unkown error json response when getting ${url}`);
            }
            return this.renderErrorJsonFormat({url,response,contentType});
        },
        printViaPreview: function(){
            let newUrl = wiki.url(`${wiki.pageTag}/preview`,{browserPrintAfterRendered:"1"});
            window.open(newUrl);
        },
        openDefaultLink: function(){
            let newUrl = this.appendSourceUrl(this.urls.external);
            window.location = newUrl;
        },
        renderDefaultError: function(error){
            if (error.message === '===Do Nothing==='){
                return;
            }
            this.messageType = 'danger';
            if (this.isAdmin){
                this.message = this.t('errorforadmin',{error:error.toString()})
                if (error.lineNumber != undefined && error.fileName != undefined){
                    console.error(`${error.name} : ${error.message}, in file ${error.fileName}, line ${error.lineNumber}`)
                }
            } else {
                this.message = this.t('errorforuser')
                setTimeout(this.printViaPreview,2000);
            }
            this.buttonAction = this.printViaPreview;
            this.buttonTitle = this.t('printviapreview');
        },
        renderDomainNotAuthorized: function(url){
            return Promise.reject(this.t('errorforexternaleurldomainnotauthorized',{
                extUrl: url,
                helpLink: this.renderHelpUrlButton()
            }));
        },
        renderHelpUrlButton: function(){
            let helpUrl = wiki.url('doc#/tools/publication/README?id=configuration-serveur-wakkaconfigphp');
            let shortHelpUrl = wiki.url('doc#/tools/publication');
            return `<a href="${helpUrl}" class="btn btn-xs btn-info" target="blank">${shortHelpUrl}</a>`;
        },
        renderErrorJsonFormat: function ({url,response,contentType}){
            return Promise.reject(`Bad format of response to url '${url}' : status '${response.status}'  => '${response.statusText}', but json waited with error = true, obtained : ${contentType}`);
        },
        t: function(text, replacements = null){
            if (replacements === null || typeof replacements != "object"){
                replacements = {};
            }
            if (text in this.translations){
                let message = this.translations[text]
                let canContinue = true;
                let eraseTimeoutId = setTimeout(()=>{
                    canContinue = false;
                },3000);
                for (var key in replacements) {
                    while (canContinue && message.includes(`{${key}}`)){
                        message = message.replace(`{${key}}`,replacements[key])
                    }
                }
                if (!canContinue && this.isAdmin){
                    console.log({abortedTranslation:{text,replacements,message}})
                }
                if (canContinue){
                    clearTimeout(eraseTimeoutId);
                }
                return message;
            } else {
                return text;
            }
        },
    },
    mounted(){
        let baseEl = isVueJS3 ? this.$el.parentNode : this.$el;
        baseEl.addEventListener('dblclick',function(e) {
          return false;
        });
        this.isAdmin = (baseEl.dataset.isAdmin === true || baseEl.dataset.isAdmin === "true");
        this.hash = baseEl.dataset.hash ?? '';
        this.pageTag = baseEl.dataset.pageTag ?? '';
        this.sourceUrl = baseEl.dataset.sourceUrl ?? '';
        this.getUrlOfPdfService(baseEl)
          .then(this.checkUrls)
          .then(this.getPdf)
          .then(({blob,fileName})=>{
            this.createPdfLink(blob,fileName);
            this.clickPdfLink();
          })
          .catch(this.renderDefaultError);
    },
};

if (isVueJS3){
    let app = Vue.createApp(appParams);
    app.config.globalProperties.wiki = wiki;
    app.config.globalProperties._t = _t;
    rootsElements.forEach(elem => {
        app.mount(elem);
    });
} else {
    Vue.prototype.wiki = wiki;
    Vue.prototype._t = _t;
    rootsElements.forEach(elem => {
        new Vue({
            ...{el:elem},
            ...appParams
        });
    });
}