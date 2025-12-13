/* Admin init for Flatpickr draw date + timezone selector */
(function($){
  $(function(){
    // Populate timezone select
    var tzSelect = $('#_raff_draw_tz');
    if (tzSelect.length && window.raffAllAdminData && Array.isArray(window.raffAllAdminData.timezones)) {
      var tzs = window.raffAllAdminData.timezones;
      tzSelect.empty();
      tzSelect.append($('<option>').val('').text('Select timezone'));
      tzs.forEach(function(tz){
        tzSelect.append($('<option>').val(tz).text(tz));
      });
      var current = tzSelect.data('current');
      if (current) tzSelect.val(current);
    }

    // Initialize flatpickr on the draw input
    var $input = $('#_raff_draw_date_local');
    if ($input.length && typeof flatpickr !== 'undefined') {
      flatpickr($input[0], {
        enableTime: true,
        time_24hr: true,
        dateFormat: 'Y-m-d H:i',
        allowInput: true,
      });
    }
  });
})(jQuery);

