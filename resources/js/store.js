import Alpine from 'alpinejs';
import {picker} from './product-picker';

Alpine.data('productPicker', function () { return picker(JSON.parse(this.$el.dataset.product)); });
window.Alpine = Alpine;
Alpine.start();
