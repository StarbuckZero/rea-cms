const allDay = document.querySelector('[data-event-all-day]');
const updateTimes = () => {
  document.querySelectorAll('[data-event-time]').forEach((input) => {
    input.disabled = allDay.checked;
    input.required = !allDay.checked;
  });
};
if (allDay) {
  allDay.addEventListener('change', updateTimes);
  updateTimes();
}
const image = document.querySelector('[data-event-image]');
const preview = document.querySelector('[data-event-image-preview]');
if (image && preview) {
  image.addEventListener('change', () => {
    preview.hidden = image.value === '';
    if (image.value) preview.src = `/media/${encodeURIComponent(image.value)}`;
    else preview.removeAttribute('src');
  });
}
