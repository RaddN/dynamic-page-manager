jQuery(document).ready(function($) {
    // Handle click on "Import Existing Page" buttons
    $('.import_existing_page').on('click', function() {
        // Show the import page selection modal
        $('#import_existing_page_modal').show();
        
        // Store data attributes for later use
        $('#template_title_hidden').val($(this).data('template-title'));
        $('#child_page_hidden').val($(this).data('child-page'));
        $('#parent_slug_hidden').val($(this).data('parent-slug'));
        $('#existing_page_hidden').val($(this).data('existing-page'));
    });
    
    // Close modal when clicking the close button
    $('.close-modal').on('click', function() {
        $('#import_existing_page_modal').hide();
    });
    
    // Handle selection of page to import
    $('#select_page_to_import').on('change', function() {
        if ($(this).val()) {
            $('#import_selected_page_btn').prop('disabled', false);
        } else {
            $('#import_selected_page_btn').prop('disabled', true);
        }
    });
    
    // Handle import button click
    $('#import_selected_page_btn').on('click', function() {
        const pageId = $('#select_page_to_import').val();
        if (!pageId) return;
        // Show loading indicator
        $('#import_loading').show();
        
        // Prepare data for AJAX request
        const data = {
            'action': 'dynapama_import_existing_page',
            'nonce': dynapamaData.nonce,
            'page_id': pageId,
            'template_title': $('#template_title_hidden').val(),
            'child_page': $('#child_page_hidden').val(),
            'parent_slug': $('#parent_slug_hidden').val(),
            'existing_page': $('#existing_page_hidden').val()
        };

        
        // Send AJAX request to get page data
        $.post(dynapamaData.ajaxUrl, data, function(response) {
            if (response.success) {
                // Hide modal
                $('#import_existing_page_modal').hide();
                // Submit form with the imported data
                let formHtml = '<form id="import_data_form" method="post" action="">';
                formHtml += '<input type="hidden" id="dynapama_nonce" name="dynapama_nonce" value="88cdd84e3a">';
                formHtml += '<input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=page-management">';
                formHtml += '<input type="hidden" name="import_template" value="1">';
                formHtml += '<input type="hidden" name="template_title" value="' + data.template_title + '">';
                formHtml += '<input type="hidden" name="child_page" value="' + data.child_page + '">';
                formHtml += '<input type="hidden" name="parent_slug" value="' + data.parent_slug + '">';
                formHtml += '<input type="hidden" name="existing_page" value="' + data.existing_page + '">';
                formHtml += '<input type="hidden" name="page_id" value="' + pageId + '">';
                formHtml += '<input type="hidden" name="imported_page_title" value="' + response.data.title + '">';
                formHtml += '<input type="hidden" name="imported_page_slug" value="' + response.data.slug + '">';
                formHtml += '</form>';
                
                $('body').append(formHtml);
                $('#import_data_form').submit();
            } else {
                alert('Error importing page: ' + response.data);
                $('#import_loading').hide();
            }
        }).fail(function() {
            alert('Server error occurred while importing page.');
            $('#import_loading').hide();
        });
    });
    
    // Content selection functionality for dynamic fields
    $(document).on('click', '.content-select-btn', function() {
        const fieldId = $(this).data('field-id');
        const contentPreview = $(this).siblings('.content-preview');
        
        if (contentPreview.is(':visible')) {
            contentPreview.hide();
        } else {
            // Hide all other previews
            $('.content-preview').hide();
            contentPreview.show();
        }
    });
    
    // Handle content selection
    $(document).on('click', '.select-content', function() {
        const content = $(this).data('content');
        const fieldId = $(this).closest('.content-preview').data('field-id');
        
        // Update the field value
        $('#' + fieldId).val(content);
        
        // Hide the preview
        $(this).closest('.content-preview').hide();
    });
});