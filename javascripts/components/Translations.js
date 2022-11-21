export default {
    methods: {
        loadTranslationsIntoRoot: function(){
            let translations = {};
            for (const name in this.$scopedSlots) {
                if (name != "default" && typeof this.$scopedSlots[name] == "function"){
                    let slot = (this.$scopedSlots[name])();
                    if (typeof slot == "object"){
                        translations[name] = slot[0].text;
                    }
                }
            }
            this.$root.translations = translations;
        }
    },
    mounted(){
        this.loadTranslationsIntoRoot();
    },
    template: `
      <div></div>
    `
  }