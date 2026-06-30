function formatPhoneInput(el) {
    el.addEventListener('input', () => {
        const digits = el.value.replace(/\D/g, '').slice(0, 11);
        el.value = digits.length > 3 ? digits.slice(0, 3) + '-' + digits.slice(3) : digits;
    });
}

document.querySelectorAll('input[data-phone-format]').forEach(formatPhoneInput);
