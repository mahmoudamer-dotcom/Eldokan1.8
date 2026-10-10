jQuery(document).ready(function ($) {  
    loadwidget();
});
 
let currentIndex = 0; // Make currentIndex a global variable
 
function loadwidget(total = null) {
    if (document.getElementById('valuWidget')) {
        let price;
        if (total === null || isNaN(parseFloat(total))) {
            price = parseFloat(wc_admin_settings.price) || 0; // Fallback to 0 if wc_admin_settings.price is undefined or invalid
        } else {
            price = parseFloat(total).toFixed(2); // Ensure price is a float with 2 decimals
        }
        jQuery.ajax({
            url: wc_admin_settings.ajax_url,
            type: 'POST',
            data: {
                action: 'valu_widget',
                _ajax_nonce: wc_admin_settings.nonce,
                price: price
            },
            success: function (response) {
                if (response.success) {
                    if (response.data.length !== 0) {
                        const widgetresults = response.data;
                        const widgetContainer = document.getElementById('render_widget');
 
                        widgetContainer.classList.remove("render_widget");
                        // Store `widgetresults` in localStorage

                        localStorage.setItem('widgetresults', JSON.stringify(widgetresults));

                        loadMoreRecords([...widgetresults].reverse());


                        if(jQuery('#load-more-link').length){
                            document.getElementById('load-more-link').addEventListener('click', function (event) {
                                event.preventDefault();
                                openModal(widgetresults);
                            });
                        }

                        if(jQuery('#close-modal').length){
                            document.getElementById('close-modal').addEventListener('click', function () {
                                document.getElementById('myModal').style.display = 'none';
                            });
                        }
 
                        window.addEventListener('click', function (event) {
                            if (event.target === document.getElementById('myModal')) {
                                document.getElementById('myModal').style.display = 'none';
                            }
                        });
                    }
                }
            }
        });
    }
}
 
function loadMoreRecords(widgetresults) {
    const recordsPerPage = 1; // Number of records to load per batch
    const widgetContainer = document.getElementById('render_widget');
 
    // If this is the first load, clear the container and add the wrapper structure
    if (currentIndex === 0) {
        widgetContainer.innerHTML = `<br/>
            <div class="container">
                <div class="info" id="info-section">
                    <!-- Installment plans will be added here -->
                   
                </div>
                <div class="logo">
                    <img src="${wc_admin_settings.image_url}" alt="VALU Logo" class="logo">
                </div>
            </div>
        `;
    }
 
    const infoSection = document.getElementById('info-section');
 
    // Get the next batch of records
    const nextRecords = widgetresults.slice(currentIndex, currentIndex + recordsPerPage);
    // Append the new records to the info section
    nextRecords.forEach((resultwidget) => {
        const recordHTML = `
        <span class="paragraph" style="display: block; white-space: nowrap;">
            Installment Plans starting from EGP ${resultwidget.emi} per month.<br>
            <span style="font-weight: bold;">
                ${resultwidget.adminFees == 0 && resultwidget.downPayment == 0 
                    ? "No interest fee • No admin fee"
                    : `${resultwidget.adminFees == 0 ? "No interest fee" : `Admin Fee: EGP ${resultwidget.adminFees}`} 
                       • ${resultwidget.downPayment == 0 ? "No admin fee" : `Down Payment: EGP ${resultwidget.downPayment}`}`
                }
            </span>
        </span>
    `;
    

        infoSection.innerHTML += recordHTML;
    });
    const loadMoreLink = document.createElement('a');
    loadMoreLink.href = '#popup';
    loadMoreLink.id = 'load-more-link';
    loadMoreLink.classList.add('learn-more');
    loadMoreLink.textContent = 'Learn more';
   
    infoSection.appendChild(loadMoreLink);
   
    // Add the "Learn more" button if there are more records to load
    if (currentIndex + recordsPerPage < widgetresults.length) {
        if (!document.getElementById('load-more-link')) {
           
            // Attach an event listener to load more records when "Learn more" is clicked
            document.getElementById('load-more-link').addEventListener('click', function (event) {
                event.preventDefault();
                loadMoreRecords(widgetresults); // Load the next batch
            });
        }
    }
 
    // Increment the current index for the next batch
    currentIndex += recordsPerPage;
    var totalFieldset = jQuery('.wc-block-cart__payment-options');
    if(totalFieldset.length == 0){
        totalFieldset = jQuery('.wp-block-woocommerce-cart-order-summary-block  .wc-block-components-totals-footer-item');
    }

    if (totalFieldset.length) {
        totalFieldset.after(widgetContainer);
    }else if(jQuery('form.cart').length){
        jQuery('form.cart').before(widgetContainer);
    }else if(jQuery('.cart_totals .wc-proceed-to-checkout').length){
        jQuery('.cart_totals .wc-proceed-to-checkout').before(widgetContainer);
    }else if(jQuery('.wc-block-mini-cart__footer-actions').length){
       jQuery('.wc-block-mini-cart__footer-actions').after(buttonHtml);
    }else{
        jQuery('#render_widget').remove();
    }
 
    // Hide the "Learn more" link if all records have been loaded
    if (currentIndex >= widgetresults.length) {
        const learnMoreLink = document.getElementById('load-more-link');
        if (learnMoreLink) {
            learnMoreLink.style.display = 'none';
        }
    }
}
 
function openModal(widgetresults) {
    const modal = document.getElementById('myModal');
    const modalContent = document.getElementById('modal-content');
    modalContent.innerHTML = ""; // Clear existing modal content
 
    // Add modal header
    let modalHeader = document.createElement("div");
    modalHeader.className = "modal-header";
 
    let closeButton = document.createElement("span");
    closeButton.className = "close";
    
    closeButton.innerHTML = wc_admin_settings.close_icon;
   
    closeButton.onclick = function () {
        modal.style.display = "none";
    };
    modalHeader.appendChild(closeButton);
 
    let modalLogo = document.createElement("img");
    modalLogo.src = wc_admin_settings.image_url;
    modalLogo.alt = "ValU logo";
    modalLogo.className = "logo";
    modalHeader.appendChild(modalLogo);
 
    modalContent.appendChild(modalHeader);
 
    // Check if widgetresults is valid
    if (!widgetresults || widgetresults.length === 0) {
        const noResults = document.createElement("span");
        noResults.textContent = "No EMI options available at the moment.";
        modalContent.appendChild(noResults);
        return;
    }
 
    // Show number of EMI options dynamically
    let totalOptions = widgetresults.length;
    let visibleCount = 6; // Initially show 5 results
    let hiddenCount = totalOptions - visibleCount; // Remaining options
 
    let optionsSection = document.createElement("div");
    optionsSection.className = "options";
 
    let moreText = document.createElement("span");
    moreText.className = "more";
    moreText.textContent = `More than ${totalOptions-1} EMI options available for you`;
    optionsSection.appendChild(moreText);
 
    let table = document.createElement("div");
    table.className = "table";
 
    widgetresults.forEach((resultwidget, index) => {
        let details = document.createElement("div");
        details.className = "details";
        if (index >= visibleCount) {
            details.style.display = "none"; // Hide extra results initially
        }
 
        let text = document.createElement("span");
        text.textContent = `EGP ${resultwidget.emi} x ${resultwidget.tenorMonth} month`;
        details.appendChild(text);
 
        let rightText = document.createElement("span");
        rightText.className = "right";
        let fees = [];

        if (resultwidget.adminFees) {
            fees.push("NO ADMIN FEE");
        } else {
            fees.push("NO ADMIN FEES");
        }

        if (resultwidget.downPayment) {
            fees.push("NO INTEREST FEE");
        } else {
            fees.push("NO INTEREST FEES");
        }

        rightText.textContent = fees.join(", ");

        details.appendChild(rightText);
 
        table.appendChild(details);
    });
 
    optionsSection.appendChild(table);
 
    // More Options Button
    if (hiddenCount > 0) {
        let moreOptions = document.createElement("span");
        moreOptions.className = "more-options";
        moreOptions.textContent = `+${hiddenCount} more`;
 
        moreOptions.onclick = function () {
            let hiddenItems = table.querySelectorAll(".details[style='display: none;']");
            hiddenItems.forEach(item => item.style.display = "flex"); // Show hidden items
            moreOptions.style.display = "none"; // Hide the "+X more" button after expanding
        };
 
        optionsSection.appendChild(moreOptions);
    }
 
    modalContent.appendChild(optionsSection);
 
    // Add How it Works section
    let howItWorksSection = document.createElement("div");
    howItWorksSection.className = "how-it-works";
    
    let br = document.createElement("br");
    howItWorksSection.appendChild(br);

    let howText = document.createElement("span");
    howText.className = "how";
    howText.textContent = "How it works";
    howItWorksSection.appendChild(howText);
 
    let arrowsContainer = document.createElement("span");
    arrowsContainer.className = "arrows";
 
    const steps = [
        "Enter your Mobile Number registered with ValU",
        "Select the Plan",
        "Enter the OTP to complete the payment"
    ];
 
    steps.forEach(step => {
        let sentence = document.createElement("span");
        sentence.className = "sentence";
        sentence.innerHTML = `<span class="arrow"><svg xmlns="http://www.w3.org/2000/svg" width="9" height="16" viewBox="0 0 9 16" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.180771 0.180771C0.237928 0.123469 0.305828 0.0780066 0.380583 0.0469869C0.455337 0.0159672 0.535477 0 0.616412 0C0.697347 0 0.777487 0.0159672 0.852241 0.0469869C0.926996 0.0780066 0.994896 0.123469 1.05205 0.180771L8.4358 7.56452C8.4931 7.62168 8.53857 7.68958 8.56959 7.76433C8.60061 7.83909 8.61657 7.91923 8.61657 8.00016C8.61657 8.0811 8.60061 8.16124 8.56959 8.23599C8.53857 8.31074 8.4931 8.37865 8.4358 8.4358L1.05205 15.8196C0.936514 15.9351 0.779809 16 0.616412 16C0.453015 16 0.29631 15.9351 0.180771 15.8196C0.0652316 15.704 0.000322157 15.5473 0.000322157 15.3839C0.000322157 15.2205 0.0652316 15.0638 0.180771 14.9483L7.13011 8.00016L0.180771 1.05205Z" fill="#737373"/></svg></span>${step}`;
        arrowsContainer.appendChild(sentence);
    });
 
    howItWorksSection.appendChild(arrowsContainer);
    modalContent.appendChild(howItWorksSection);
 
    // Buttons
    let buttonContainer = document.createElement("div");
    buttonContainer.className = "buttons";
 
    let continueShoppingBtn = document.createElement("button");
    continueShoppingBtn.className = "continue-shopping";
    continueShoppingBtn.textContent = "Continue Shopping";
    continueShoppingBtn.onclick = function (event) {
        event.preventDefault();
        window.location.href = wc_admin_settings.shop_url;
    };
    let buyNowBtn = document.createElement("button");
    buyNowBtn.className = "buy-now";
    buyNowBtn.textContent = "Buy now with ValU";
    buyNowBtn.onclick = function (event) {
        event.preventDefault();
        let productID = wc_admin_settings.product_id; 
        if (typeof wc_admin_settings !== 'undefined' && wc_admin_settings.product_id) {
            jQuery.ajax({
                url: wc_admin_settings.ajax_url, // WordPress AJAX URL
                type: 'POST',
                data: {
                    action: 'add_to_cart',
                    _ajax_nonce: wc_admin_settings.nonce, // Security nonce
                    product_id: wc_admin_settings.product_id, // The product ID
                    quantity: 1
                },
                success: function(response) {
                    if (response.success) {
                        console.log("Product added:", response);
                        window.location.href = wc_admin_settings.checkout_url + "?select_valu=" + wc_admin_settings.integration_id; // Redirect to checkout
                    } else {
                        console.error("Failed to add product:", response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", error);
                }
            });
        }
        else
        {
            window.location.href = wc_admin_settings.checkout_url + "?select_valu=" + wc_admin_settings.integration_id; // Redirect to checkout
        }
    };
 
    buttonContainer.appendChild(continueShoppingBtn);
    buttonContainer.appendChild(buyNowBtn);
 
    modalContent.appendChild(buttonContainer);
 
    // Show modal
    modal.style.display = "block";
}
 
document.addEventListener("DOMContentLoaded", function () {
    if (window.location.hash === "#popup") {
        let storedWidgetResults = localStorage.getItem("widgetresults");

        if (storedWidgetResults) {
            openModal(JSON.parse(storedWidgetResults)); // Call modal with stored data
        }
    }
});


 
jQuery(document).ready(function($) {
    // Attach a listener for the updated_cart_totals event
    $(document.body).on('updated_cart_totals', function() {
        // Get the formatted cart total
            var total = $('div.cart_totals tr.order-total span.woocommerce-Price-amount').text();
            // Remove currency symbols and commas
            total = total.replace(/[^\d.-]/g, '');
            // Convert to a float and format with 2 decimals
            total = parseFloat(total).toFixed(2);
            loadwidget(total);
    });
});
 
jQuery(document).ready(function($) {
    // Polling interval to check if the element is available
    const checkInterval = setInterval(function() {
        const targetElement = document.querySelector('.wc-block-components-totals-item__value');
       
        // Check if the target element exists
        if (targetElement) {
            clearInterval(checkInterval); // Stop checking once element is found
            observeTotalChanges(targetElement); // Start observing changes
        }
    }, 100); // Check every 100 milliseconds
});
 
// Function to set up the MutationObserver on the target element
function observeTotalChanges(targetElement) {
    const callback = function(mutationsList) {
        for (let mutation of mutationsList) {
            if (mutation.type === 'childList' || mutation.type === 'characterData') {
                // Get the new value after the change
                const newValue = jQuery(targetElement).text().replace(/[^\d.-]/g, ''); // Remove any non-numeric characters
                console.log("New total value:", newValue);
                onTotalValueChange(newValue); // Call the function with the new value
                break; // Exit loop once we handle the change
            }
        }
    };
 
    // Create a MutationObserver and configure it to listen for child and character data changes
    const observer = new MutationObserver(callback);
    observer.observe(targetElement, {
        characterData: true,
        childList: true,
        subtree: true
    });
}
 
// Function to call when the target element's value changes, with the new value passed as a parameter
function onTotalValueChange(newValue) {
    loadwidget(newValue); // Pass the new value to the widget loader or any function that needs it
}
 

jQuery(document).ready(function ($) {
    function selectValUPaymentClassic() {
        let paymentOption = $("input[id*='"+wc_admin_settings.integration_id +"-valu']");
        if (paymentOption.length > 0) {
            $('input[name="payment_method"]').prop("checked", false);
            paymentOption.prop("checked", true).trigger("click").trigger("change");
            $(".payment_box").hide(); // Hide all descriptions
            paymentOption.closest("li").find(".payment_box").show(); // Show ValU description
            return true;
        }
        return false;
    }
    let urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has("select_valu") ) {
        setTimeout(selectValUPaymentClassic, 1000);
    }
});
function selectValUPaymentBlock() {
    var methodID = jQuery("input[id*='"+wc_admin_settings.integration_id+"-valu']").val();
       // Example: Change to methodID
    // updatePaymentMethod(methodID);
    const targetOption = jQuery(`input[name="radio-control-wc-payment-method-options"][value="${methodID}"]`);

    if (!targetOption.length) {
        console.warn(`Payment method "${methodID}" not found.`);
        return;
    }

    // Step 1: Uncheck all payment methods
    jQuery('input[name="radio-control-wc-payment-method-options"]').prop('checked', false).removeAttr('checked');
    jQuery('input[name="radio-control-wc-payment-method-options"]').off().unbind();
    
    // Step 2: Remove selected styles and hide all content
    jQuery('.wc-block-components-radio-control-accordion-option')
        .removeClass('is-selected wc-block-components-radio-control-accordion-option--checked-option-highlighted');
    jQuery('.wc-block-components-radio-control__option').removeClass('wc-block-components-radio-control__option-checked');
    jQuery('.wc-block-components-radio-control-accordion-content').removeClass('is-open').hide();

    // Step 3: Manually select the target payment method
    targetOption.checked = true; // Select it
    targetOption.attr('checked', 'checked'); // Ensure attribute is set

    // targetOption.dispatchEvent(new Event('click', { bubbles: true }));
    // targetOption.dispatchEvent(new Event('change', { bubbles: true }));

    setTimeout(function () {
    //     targetOption.dispatchEvent(new Event('click', { bubbles: true }));
    // targetOption.dispatchEvent(new Event('change', { bubbles: true }));

// const paymentMethod = document.querySelector('input[name="radio-control-wc-payment-method-options"][value="'+methodID+'"]');

// if (paymentMethod) {
    // paymentMethod.checked = true;
    // paymentMethod.dispatchEvent(new Event('click', { bubbles: true }));
    // paymentMethod.dispatchEvent(new Event('change', { bubbles: true }));
// }

        targetOption.trigger('click').trigger('change');
    }, 500);


    // Step 4: Show the corresponding content section
    const accordionContent = jQuery(`.wc-block-components-radio-control-accordion-content[data-payment-method="${methodID}"]`);
    accordionContent.addClass('is-open').show();

    // Step 5: Add selection styles
    const parentAccordion = targetOption.closest('.wc-block-components-radio-control-accordion-option');
    jQuery(parentAccordion).addClass('is-selected wc-block-components-radio-control-accordion-option--checked-option-highlighted');

    const parentLabel = targetOption.closest('.wc-block-components-radio-control__option');
    jQuery(parentLabel).addClass('wc-block-components-radio-control__option-checked');


}

function updatePaymentMethod(paymentMethod) {
    jQuery.ajax({
    url: '/wp-json/wc/store/v1/cart',
    method: 'POST',
    contentType: 'application/json',
    beforeSend: function(xhr) {
        xhr.setRequestHeader('X-WC-Store-API-Nonce', wcSettings.storeApiNonce); // Security nonce
    },

    data: JSON.stringify({
        payment_method: paymentMethod // Change to your desired payment method slug
    }),
    success: function(response) {
        console.log('Payment method updated:', response);
        // Trigger WooCommerce Blocks to recognize the new payment method
        jQuery(document.body).trigger('wc-blocks-update-checkout');
    },
    error: function(xhr, status, error) {
        console.error('Error updating payment method:', error);
    }
});

}

// // Automatically trigger selection if URL parameter is present
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has("select_valu") ) {
        setTimeout(selectValUPaymentBlock, 2500);
    }
});