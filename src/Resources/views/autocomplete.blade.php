@push('scripts')
    <script>
        $.fn.justNumber = function () {
            this.keypress(function (e) {
                if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
                    return false;
                }
            });
        }

        $(document).ready(function () {
            $('#country').val('BR');

            $('[name="postcode"]').justNumber();
            $('[name="postcode"]').keyup(function () {
                var postcode = $(this).val();
                if (postcode.length >= 8) {
                    $.getJSON('{{ route('correios.autocomplete') }}', {
                        postcode: postcode
                    })
                    .then(function (result) {

                    });
                    return false;
                }
            });
        });
    </script>
@endpush