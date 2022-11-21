import SpinnerLoader from './SpinnerLoader.js'

export default {
    props: ['name','value','text'],
    components: {SpinnerLoader},
    computed: {
        isChecked: function(){
            return this.value == 2 ? {checked:true} : false;
        },
    },
    template: `
        <div class="input-group mb-3">
            <label :for="name" :class="{['loading-cursor']:value === 1}">
                <input 
                    type="checkbox" 
                    :id ="name" 
                    :name="name"
                    value="1" 
                    disabled 
                    :checked="isChecked"/>
                <span v-html="text"></span>
            </label>
            &nbsp;
            <SpinnerLoader :size="1" :height="25" v-if="value === 1"/>
            <span v-if="value === 3">&#10060;</span>
        </div>
    `
}