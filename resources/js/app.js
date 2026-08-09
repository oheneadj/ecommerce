document.addEventListener('livewire:navigating', () => {
    document.documentElement.setAttribute('data-navigating', '');
});

document.addEventListener('livewire:navigated', () => {
    document.documentElement.removeAttribute('data-navigating');
});
