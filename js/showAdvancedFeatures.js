const toggleLabel = document.getElementById("advancedFeaturesToggleLabel");
const advancedContainer = document.getElementById("advancedFeaturesContainer");

toggleLabel.addEventListener("click", () => {
    const isHidden =
        getComputedStyle(advancedContainer).display === "none";

    if (isHidden) {
        advancedContainer.style.display = "flex";
        toggleLabel.classList.add("active");
    } else {
        advancedContainer.style.display = "none";
        toggleLabel.classList.remove("active");

        // advancedContainer
        //     .querySelectorAll(".active")
        //     .forEach(feature => {
        //         feature.classList.remove("active");
        //     });
    }
});
