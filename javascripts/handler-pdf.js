import SpinnerLoader from './components/SpinnerLoader.js'
import Step from './components/Step.js'
import Translations from './components/Translations.js'

let rootsElements = ['.pdf-handler-container'];
let isVueJS3 = (typeof Vue.createApp == "function");

let appParams = {
    components: { Translations, Step, SpinnerLoader },
    data: function() {
        return {
            abortController : null,
            browserLoaded: 0,
            buttonAction: null,
            buttonType: 'primary',
            buttonTitle: '',
            creatingPdf: 0,
            downloadFileName: '',
            downloadLink: '',
            finish: false,
            hash: '',
            isAdmin: null,
            isIframe: null,
            message: '',
            messageType: 'danger',
            pageTag:'',
            pageLoadedByBrowser: 0,
            pdfDownloaded: 0,
            pdfServiceContacted: 0, // 0 = waiting, 1 = running, 2 = success, 3 = error
            refresh: false,
            sourceUrl: '',
            translations: {},
            uid: '',
            unsetTimerId: null,
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
            if (this.getUuid.length == 0){
                this.uuid = this.getUuid();
            }
            return `${serverUrl}${firstDelimiter}urlPageTag=${encodeURIComponent(this.pageTag)}&url=${encodeURIComponent(this.sourceUrl)}&hash=${this.hash}&uuid=${this.uuid}&forceNewFormat=1${this.refresh ? '&refresh=1':''}`;
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
            this.stopFetch();
            this.abortController = new AbortController();
            return fetch(url,{referrer:wiki.url(this.pageTag),signal:this.abortController.signal})
                .then((response)=>{
                    if (!response.ok && [401,403,404].includes(response.status)){
                        return Promise.reject(`${response.status} => ${response.statusText}`);
                    }
                    return response;
                })
                .catch((error)=>{
                    if (typeof error == 'DOMException' && error.name == 'AbortError'){
                        return Promise.reject(new Error('===Do Nothing==='));
                    }
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
        clearTimer: function(){
            if (this.unsetTimerId != null){
                try {
                    clearTimeout(this.unsetTimerId);
                } catch (error) {}
                this.unsetTimerId = null
            }
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
        createupdateStatusTimer: function(url,uuid){
            this.clearTimer();
            if (this.isAdmin){
                let urlToFollow = this.getUrlToUpdateStatus(url,uuid);
                if (urlToFollow.length > 0){
                    this.createupdateStatusTimerDirect(urlToFollow);
                }
            }
        },
        createupdateStatusTimerDirect: function(url){
            this.clearTimer();
            this.unsetTimerId = setTimeout(()=>{
                this.updateStatus(url)
            },400);
        },
        getPdf: async function(urls = {local:'',external:''}){
            this.pdfServiceContacted = 1;
            let urlToContact = urls.local.length > 0 ? urls.local : urls.external;
            let fullUrl = this.appendSourceUrl(urlToContact);
            this.messageType = 'info';
            this.message = this.t('creatingpdf');
            this.createupdateStatusTimer(fullUrl,this.uuid);
            return await this.contactServer(fullUrl)
                .then((response)=>{
                    this.clearTimer();
                    this.message = '';
                    this.pdfServiceContacted = 2;
                    this.pdfDownloaded = 1;
                    let fileName = `${this.pageTag}-${this.hash}.pdf`;
                    return response.blob().then((blob)=>{
                        this.pdfDownloaded = 2;
                        this.updateVariables([1,1,1,1,7,1],false);
                        return {blob,fileName};
                    });
                })
                .catch((error)=>{
                    this.clearTimer();
                    if (typeof error != "object" || error.message !== '===Do Nothing==='){
                        this.message = '';
                    }
                    if (this.pdfServiceContacted < 2){
                        this.pdfServiceContacted = 3;
                    }
                    if (this.pdfDownloaded > 0){
                        this.pdfDownloaded = 3;
                    }
                    this.updateVariables([],true);
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
        getUrlToUpdateStatus: function(url,uuid){
            try {
                let baseUrl = url;
                let urlObject = new URL(url);
                let hash = urlObject.hash;
                if (hash.length > 0){
                    baseUrl = baseUrl.replace(hash,'');
                }
                let search = urlObject.search
                if (search.length > 0){
                    baseUrl = baseUrl.replace(search,'');
                }
                baseUrl = baseUrl.replace('api/pdf/getPdf','');

                let newUrl = new URL(baseUrl);
                newUrl.search = `?api/pdf/getStatus/${uuid}`;
                newUrl = newUrl.toString();
                return newUrl;

            } catch (error) {
                return '';
            }
        },
        getUuid: function(){
            if (typeof crypto != "undefined" && typeof crypto.randomUUID == "function"){
                return crypto.randomUUID();
            } else {
                let nbChars = 35;//122-97+1+57-48;
                return (new Array(5))
                    .fill(0)
                    .map(()=>{
                        return (new Array(5))
                            .fill(0)
                            .map(()=>{
                                let idx = Math.round(Math.random()*nbChars);
                                return String.fromCharCode((idx<10?48:87)+idx);
                            })
                            .join('');
                    })
                    .join('-');
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
                    if ('pdfCreationError' in jsonContent.cause && jsonContent.cause.pdfCreationError === true){
                        if (this.isAdmin){
                            console.log({htmlDuringError:jsonContent.cause.pdfCreationErrorHTML || ''})
                        }
                        return Promise.reject(this.t('errorforexternalwhilecreatingpdf',{
                            url:url,
                            error: jsonContent.cause.pdfCreationErrorMessage || 'unknown'
                        }));
                    }
                }
                return Promise.reject(`Unkown error json response when getting ${url}`);
            }
            return this.renderErrorJsonFormat({url,response,contentType});
        },
        printViaPreview: function(){
            this.openWindowAndTest(this.sourceUrl+(this.sourceUrl.includes('?') ? '&' : '?')+'browserPrintAfterRendered=1');
        },
        openWindowAndTest(url) {
            if (window.open(url) == null){
                this.message = this.message + '<br/><br/><b><span style="text-transform: uppercase;">' + this.t('popuptovalidate')+'</span></b>'
            }
        },
        openDefaultLink: function(){
            let newUrl = this.appendSourceUrl(this.urls.external);
            window.location = newUrl;
        },
        renderDefaultError: function(error){
            this.finish = true;
            if (error.message === '===Do Nothing==='){
                return;
            }
            this.messageType = this.isAdmin ? 'danger' : 'info';
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
            let helpUrl = wiki.url('doc#/tools/publication/docs/README');
            let shortHelpUrl = wiki.url('doc#/tools/publication');
            return `<a href="${helpUrl}" class="btn btn-xs btn-info" target="blank">${shortHelpUrl}</a>`;
        },
        renderErrorJsonFormat: function ({url,response,contentType}){
            return Promise.reject(`Bad format of response to url '${url}' : status '${response.status}'  => '${response.statusText}', but json waited with error = true, obtained : ${contentType}`);
        },
        returnToPage: function(){
            this.stopFetch();
            window.location = wiki.url(wiki.pageTag+(this.isIframe ? '/iframe' : ''));
        },
        stopFetch: function(){
            if (this.abortController !== null){
                this.abortController.abort();
            }
            this.abortController = null;
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
        updateVariables: function (data, setError){
            let intSetError = setError === true;
            if (Array.isArray(data)){
                this.updateVariablesInternal('browserLoaded','pdfServiceContacted',data[3] || undefined, 1,intSetError);
                this.updateVariablesInternal('pageLoadedByBrowser','browserLoaded',data[4] || undefined, 7,intSetError);
                this.updateVariablesInternal('creatingPdf','pageLoadedByBrowser',data[5] || undefined, 1,intSetError);

            }
        },
        updateVariablesInternal: function (name, previousName, value , waited,setError){
            this[name] = this[name] == 2 ? 2 : ((this[name] == 1 && setError) ? 3 : (value === waited  ? 2 : (value === 0 ? 3 : (this[previousName] == 2 ? (setError ? 3 : 1) : 0)))); 
        },
        updateStatus: async function(url){
            try {
                let aController = this.abortController
                if (aController === null){
                    this.abortController = new AbortController();
                    aController = this.abortController;
                }
                let jsonResponse = await fetch(url,{signal:this.abortController.signal})
                    .then((response)=>{
                        return (response.ok)
                            ? response.json()
                            : Promise.reject('response not ok');
                        }
                    );
                let jsonAsArray = Array.isArray(jsonResponse)
                    ? jsonResponse
                    : (
                        typeof jsonResponse == "object"
                        ? (new Array(1+Math.max(...(Object.keys(jsonResponse).map((k)=>Number(k)).filter((v)=>!Number.isNaN(v))))))
                            .fill(0)
                            .map((val,idx)=>idx in jsonResponse ? jsonResponse[idx] : null)
                        : []
                    );
                // contact established
                if (this.pdfServiceContacted> 0 && jsonAsArray[0] > 0){
                    if (this.pdfServiceContacted == 1 && this.isAdmin){
                        this.pdfServiceContacted = 2;
                    }
                    this.updateVariables(jsonAsArray,false);
                }
                if (!this.finish){
                    this.createupdateStatusTimerDirect(url);
                }
                
            } catch (error) {
                // do nothing if fetch error
                return null;
            }
        },
        viewPreview: function(){
            this.openWindowAndTest(this.sourceUrl);
        },
    },
    mounted(){
        let baseEl = isVueJS3 ? this.$el.parentNode : this.$el;
        baseEl.addEventListener('dblclick',function(e) {
          return false;
        });
        this.isAdmin = (baseEl.dataset.isAdmin === true || baseEl.dataset.isAdmin === "true");
        this.isIframe = (baseEl.dataset.isIframe === true || baseEl.dataset.isIframe === "true");
        this.hash = baseEl.dataset.hash ?? '';
        this.pageTag = baseEl.dataset.pageTag ?? '';
        this.sourceUrl = baseEl.dataset.sourceUrl ?? '';
        this.refresh = (baseEl.dataset.refresh === true || baseEl.dataset.refresh === "true");
        this.buttonTitle = this.t('preview');
        this.buttonType = 'info'
        this.buttonAction = this.viewPreview;
        this.getUrlOfPdfService(baseEl)
          .then(this.checkUrls)
          .then(this.getPdf)
          .then(({blob,fileName})=>{
            this.createPdfLink(blob,fileName);
            this.clickPdfLink();
            this.finish = true;
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