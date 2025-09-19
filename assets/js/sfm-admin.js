jQuery(document).ready(function($) {
    // Feed management
    let feedIndex = $('.sfc-feed-row').length;

    $('#sfc-add-feed').on('click', function() {
        const template = `
            <div class="sfc-feed-row" style="margin-bottom: 10px;">
                <input type="text"
                       name="sfc_options[feeds][${feedIndex}][name]"
                       placeholder="Calendar Name"
                       style="width: 200px;" />

                <input type="url"
                       name="sfc_options[feeds][${feedIndex}][url]"
                       placeholder="<https://calendar.google.com/calendar/ical/>..."
                       style="width: 400px;" />

                <input type="color"
                       name="sfc_options[feeds][${feedIndex}][color]"
                       value="#3788d8" />

                <button type="button" class="button sfc-remove-feed">Remove</button>
            </div>
        `;

        $('#sfc-feeds-wrapper').append(template);
        feedIndex++;
    });

    $(document).on('click', '.sfc-remove-feed', function() {
        $(this).closest('.sfc-feed-row').remove();
    });
});
