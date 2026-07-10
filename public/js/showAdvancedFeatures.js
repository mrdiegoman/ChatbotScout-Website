const toggleLabel = document.getElementById("advancedFeaturesToggleLabel");
const advancedContainer = document.getElementById("advancedFeaturesContainer");

if (toggleLabel && advancedContainer) {
    toggleLabel.addEventListener("click", () => {
        const isHidden = advancedContainer.hidden;

        if (isHidden) {
            advancedContainer.hidden = false;
            toggleLabel.classList.add("active");
        } else {
            advancedContainer.hidden = true;
            toggleLabel.classList.remove("active");
        }
    });
}
