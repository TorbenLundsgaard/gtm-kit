jQuery(document).ready(function(c){gtmkit_settings.datalayer_name;c(document.body).on("change",".edd-item-quantity",function(t){const d=c(this),a=parseInt(d.val()),e=d.data("key"),i=d.closest(".edd_cart_item").data("download-id"),n=JSON.parse(d.parent().find('input[name="edd-cart-download-'+e+'-options"]').val()),o=Object.entries(gtmkit_data.edd.cart_items);o.forEach(t=>{t[1].download.download_id!=i||void 0!==t[1].download.price_id&&t[1].download.price_id!=n.price_id||Object.assign(gtmkit_data.edd.cart_items[t[0]],{quantity:a})})})});