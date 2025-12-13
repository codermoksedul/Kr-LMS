jQuery(function($){
    const $modal = $('#cb-modal');
    
    // Modal Toggles (Certificate)
    $('#cb-add-new').click(() => $modal.fadeIn(200));
    $('#cb-close, #cb-cancel, .cb-modal-backdrop').click(() => $modal.fadeOut(200));

    // Autocomplete Helper
    function setupSearch(inputSel, dropdownSel, hiddenSel, action) {
        $(inputSel).on('keyup', function(){
            let term = $(this).val();
            if(term.length < 2) return;
            $.post(ajaxurl, { action, term }, function(res){
                let $box = $(dropdownSel);
                $box.empty().show();
                if(!res.length) $box.append('<div class="no-res">No results</div>');
                res.forEach(item => {
                    $box.append(`<div data-id="${item.id}">${item.text}</div>`);
                });
            });
        });
        
        $(document).on('click', dropdownSel + ' div', function(){
            if($(this).hasClass('no-res')) return;
            $(inputSel).val($(this).text());
            $(hiddenSel).val($(this).data('id'));
            $(dropdownSel).hide();
        });
    }

    setupSearch('#cb-user-search', '#cb-user-dropdown', '#cb-user-selected', 'cb_search_users');
    setupSearch('#cb-course-search', '#cb-course-dropdown', '#cb-course-selected', 'cb_search_courses');

    // Hide dropdowns on outside click
    $(document).click(e => {
        if(!$(e.target).closest('.cb-autocomplete').length) $('.cb-dropdown').hide();
    });

    // Generate Certificate
    $('#cb-generate').click(function(){
        let uid = $('#cb-user-selected').val();
        let cid = $('#cb-course-selected').val();
        let gr  = $('#cb-grade').val();
        let fname = $('#cb-father-name').val();
        let mname = $('#cb-mother-name').val();
        let batch = $('#cb-batch').val();
        let dates = $('#cb-date-range').val();

        if(!uid || !cid) {
            Swal.fire('Missing Data', 'Please select both student and course.', 'warning');
            return;
        }

        $(this).prop('disabled', true).text('Processing...');

        $.post(ajaxurl, {
            action: 'cb_generate_certificate',
            user_id: uid,
            course_id: cid,
            grade: gr,
            father_name: fname,
            mother_name: mname,
            batch: batch,
            date_range: dates
        }, function(res){
            if(res.success) {
                Swal.fire({
                    title: 'Success!',
                    text: 'Certificate generated successfully.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
                $('#cb-generate').prop('disabled', false).text('Generate');
            }
        });
    });

    // Delete Certificate
    $('.cb-delete-btn').click(function(){
        let id = $(this).data('id');
        let $row = $('#cb-row-'+id);

        Swal.fire({
            title: 'Are you sure?',
            text: 'This data will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(ajaxurl, {
                    action: 'cb_delete_certificate',
                    id: id
                }, function(res){
                    if(res.success) {
                        $row.fadeOut(300, function(){ $(this).remove(); });
                        Swal.fire('Deleted!', 'The certificate has been deleted.', 'success');
                    } else {
                        Swal.fire('Error', 'Could not delete.', 'error');
                    }
                });
            }
        });
    });

    // --- Leaderboard Logic ---
    const $lbModal = $('#lb-modal');
    $('#lb-add-new').click(() => $lbModal.fadeIn(200));
    $('#lb-close, #lb-cancel, .cb-modal-backdrop').click(() => $lbModal.fadeOut(200));

    // Auto-show on Leaderboard page
    if (window.location.search.includes('page=kr-lms-leaderboard')) {
        $lbModal.fadeIn(200);
    }

    // Autocomplete for Leaderboard
    setupSearch('#lb-user-search', '#lb-user-dropdown', '#lb-user-selected', 'cb_search_users');
    setupSearch('#lb-course-search', '#lb-course-dropdown', '#lb-course-selected', 'cb_search_courses');

    // Save Leaderboard
    $('#lb-save').click(function(){
        let uid   = $('#lb-user-selected').val();
        let cid   = $('#lb-course-selected').val();
        let exam  = $('#lb-exam-name').val();
        let pts   = $('#lb-points').val();
        let date  = $('#lb-date').val();

        if(!uid || !cid || !exam || !date) {
            Swal.fire('Error', 'Please fill all fields.', 'warning');
            return;
        }

        $(this).prop('disabled', true).text('Saving...');

        $.post(ajaxurl, {
            action: 'kb_lb_save',
            user_id: uid,
            course_id: cid,
            exam_name: exam,
            points: pts,
            date: date
        }, function(res){
             $('#lb-save').prop('disabled', false).text('Save Entry');
            if(res.success) {
                $lbModal.fadeOut();
                Swal.fire('Saved!', 'Leaderboard entry added.', 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', res.data.message || 'Error saving.', 'error');
            }
        });
    });

    // Delete Leaderboard
    $('.lb-delete-btn').click(function(){
        let id = $(this).data('id');
        let $row = $('#lb-row-'+id);

        Swal.fire({
            title: 'Delete this entry?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(ajaxurl, { action: 'kb_lb_delete', id: id }, function(res){
                    if(res.success) {
                        $row.fadeOut(300, function(){ $(this).remove(); });
                        Swal.fire('Deleted!', 'Entry removed.', 'success');
                    } else {
                        Swal.fire('Error', 'Could not delete.', 'error');
                    }
                });
            }
        });
    });
});
