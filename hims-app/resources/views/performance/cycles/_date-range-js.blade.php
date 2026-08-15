{{--
    Shared cycle-type → date-range logic.

    Included by both the create page and the edit modal so the range rules have
    one definition. Exposes window.himsCycleRange(type), returning
    ['YYYY-MM-DD', 'YYYY-MM-DD'] or null for an unrecognised type.

    Callers wire their own change handler, because the two hosts differ: create
    fills empty fields, while the modal must only overwrite when the user
    themselves picks a new type (a programmatic .value assignment fires no
    change event, which is what keeps an existing cycle's stored dates intact
    when the modal opens).
--}}
@once
@push('scripts')
<script>
window.himsCycleRange = (function () {
    const fmt = (d) => d.getFullYear()
        + '-' + String(d.getMonth() + 1).padStart(2, '0')
        + '-' + String(d.getDate()).padStart(2, '0');

    // Day 0 of the following month resolves to the last day of the month we want,
    // so leap years and 30/31-day months need no special casing.
    return function (type) {
        const today = new Date();
        const year  = today.getFullYear();
        let range;

        switch (type) {
            case 'annual':
                range = [new Date(year, 0, 1), new Date(year, 12, 0)];
                break;
            case 'semi_annual':
                range = today.getMonth() < 6
                    ? [new Date(year, 0, 1), new Date(year, 6, 0)]
                    : [new Date(year, 6, 1), new Date(year, 12, 0)];
                break;
            case 'quarterly': {
                const q = Math.floor(today.getMonth() / 3);
                range = [new Date(year, q * 3, 1), new Date(year, q * 3 + 3, 0)];
                break;
            }
            case 'probationary': {
                // Philippine Labor Code caps probation at six months.
                const end = new Date(year, today.getMonth() + 6, today.getDate());
                end.setDate(end.getDate() - 1);
                range = [today, end];
                break;
            }
            default:
                return null;
        }

        return [fmt(range[0]), fmt(range[1])];
    };
})();
</script>
@endpush
@endonce
