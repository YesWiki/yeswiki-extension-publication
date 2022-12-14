export default {
    props: {
        height:[String,Number],
        size: {
            type:Number,
            default: 4
        }
    },
    computed: {
      spinnerHeight() {
        return (this.height || 200) + 'px'
      },
      classSize: function(){
        return `fa-${this.size}x`;
      },
      iconClass: function(){
        return {
            fas:true,
            [this.classSize]:true,
            'fa-circle-notch':true,
            'fa-spin':true
        };
      }
    },
    template: `
      <div class="spinner-loader" :style="{height: spinnerHeight}">
        <i :class="iconClass"></i>
      </div>
    `
  }