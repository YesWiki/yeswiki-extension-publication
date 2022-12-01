import SpinnerLoader from './SpinnerLoader.js'

export default {
    props: ['name','value','text','isAdmin'],
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
            <span v-if="value === 1">&#9203;</span>
            <SpinnerLoader :size="1" :height="25" v-if="value === 1"/>
            <span v-else-if="value === 3 && isAdmin">&#10060;</span>
            <span v-else-if="value === 3 && !isAdmin">&#9889;</span>
        </div>
    `
}