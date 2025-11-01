(function(){
    function clamp(value, min, max){
        return Math.min(Math.max(value, min), max);
    }

    function getDecimals(step){
        if (!step) {
            return 1;
        }
        var parts = step.toString().split('.');
        return parts.length === 2 ? parts[1].length : 0;
    }

    function parseNumeric(value, decimal){
        if (value === undefined || value === null) {
            return NaN;
        }
        if (typeof value !== 'string') {
            value = value.toString();
        }
        value = value.trim();
        if (value === '') {
            return NaN;
        }
        if (decimal && decimal !== '.') {
            value = value.replace(decimal, '.');
        }
        return parseFloat(value);
    }

    function formatNumeric(value, decimal, decimals){
        var fixed = value.toFixed(decimals);
        if (decimal && decimal !== '.') {
            fixed = fixed.replace('.', decimal);
        }
        return fixed;
    }

    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.axs4all-slider-wrapper').forEach(function(wrapper){
            var range = wrapper.querySelector('.axs4all-slider');
            var number = wrapper.querySelector('.axs4all-slider-value');
            var output = wrapper.querySelector('.axs4all-slider-output');

            if (!range || !number) {
                return;
            }

            var min = parseFloat(range.getAttribute('min') || number.getAttribute('min') || '0');
            var max = parseFloat(range.getAttribute('max') || number.getAttribute('max') || '1');
            var step = parseFloat(range.getAttribute('step') || number.getAttribute('step') || '0.1');
            if (!step || isNaN(step)) {
                step = 0.1;
            }
            var decimalsAttr = parseInt(wrapper.getAttribute('data-decimals') || '', 10);
            var decimals = !isNaN(decimalsAttr) ? decimalsAttr : getDecimals(step);
            var decimalSeparator = wrapper.getAttribute('data-decimal') || '.';

            function syncFromRange(){
                var value = parseFloat(range.value);
                if (isNaN(value)) {
                    return;
                }
                value = clamp(value, min, max);
                number.value = value.toFixed(decimals);
                if (output) {
                    output.textContent = formatNumeric(value, decimalSeparator, decimals);
                }
            }

            function syncFromNumber(){
                var parsed = parseNumeric(number.value, decimalSeparator);
                if (isNaN(parsed)) {
                    return;
                }
                parsed = clamp(parsed, min, max);
                var ratio = parsed / step;
                if (!isFinite(ratio)) {
                    ratio = 0;
                }
                parsed = Math.round(ratio) * step;
                parsed = clamp(parsed, min, max);
                range.value = parsed.toFixed(decimals);
                number.value = parsed.toFixed(decimals);
                if (output) {
                    output.textContent = formatNumeric(parsed, decimalSeparator, decimals);
                }
            }

            range.addEventListener('input', syncFromRange);
            number.addEventListener('input', syncFromNumber);

            syncFromNumber();
        });
    });
})();

