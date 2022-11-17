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
            isAdmin: null,
            message: '',
            messageType: 'danger',
            pdfDownloaded: 0,
            pdfServiceContacted: 0, // 0 = waiting, 1 = running, 2 = success, 3 = error
            urlOfPdfServiceGet: 0,
            translations: {},
        };
    },
    computed: {
    },
    methods: {
        getUrlOfPdfService: async function(){
            this.urlOfPdfServiceGet = 1;
            try {
                return await fetch(wiki.url('?api/pdf/getUrlOfPdfService'))
                    .then((response)=>{
                        if (response.ok){
                            return response.json()
                        } else {
                            return Promise.reject(`Not possible to get url of pdf service because response code is not right : '${response.status}' => '${response.statusText}'`)
                        }
                    })
            } catch (error) {
                this.urlOfPdfServiceGet = 3;
                return Promise.reject(error)
            }
        },
        manageButton: function(event){
            if (typeof this.buttonAction == "function"){
                this.buttonAction(event);
            }
        },
        printViaPreview: function(){
            let newUrl = wiki.url(`${wiki.pageTag}/preview`,{browserPrintAfterRendered:"1"});
            window.location = newUrl;
        },
        renderDefaultError: function(error){
            this.messageType = 'danger';
            if (this.isAdmin){
                this.message = this.t('errorforadmin',{error:error.toString()})
            } else {
                this.message = this.t('errorforuser')
                setTimeout(this.printViaPreview,2000);
            }
            this.buttonAction = this.printViaPreview;
            this.buttonTitle = this.t('printviapreview');
        },
        t: function(text, replacements = {}){
            if (text in this.translations){
                let message = this.translations[text]
                for (var key in replacements) {
                    while (message.includes(`{${key}}`)){
                        message = message.replace(`{${key}}`,replacements[key])
                    }
                }
                return message;
            } else {
                return "";
            }
        },
    },
    mounted(){
        let baseEl = isVueJS3 ? this.$el.parentNode : this.$el;
        baseEl.addEventListener('dblclick',function(e) {
          return false;
        });
        this.isAdmin = (baseEl.dataset.isAdmin === true || baseEl.dataset.isAdmin === "true");
        this.getUrlOfPdfService()
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