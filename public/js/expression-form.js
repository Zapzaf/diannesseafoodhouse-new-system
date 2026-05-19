document.addEventListener("DOMContentLoaded", () => {
    // Image Preview
    const imageInput = document.getElementById("image_url");
    const imagePreview = document.getElementById("imagePreview");

    if (imageInput) {
        imageInput.addEventListener("change", function () {
            const file = this.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) {
                alert("File is too large! Maximum allowed size is 5MB.");
                imageInput.value = ""; // Clear the input
                imagePreview.classList.add("d-none"); // Hide preview
                return;
            }
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = new Image();
                img.src = e.target.result;

                img.onload = function () {
                    // Create canvas for compression
                    const canvas = document.createElement("canvas");
                    const maxWidth = 1024; // max width
                    const scaleSize = maxWidth / img.width;
                    canvas.width = maxWidth;
                    canvas.height = img.height * scaleSize;

                    const ctx = canvas.getContext("2d");
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                    // Convert canvas to blob (compressed file)
                    canvas.toBlob(
                        function (blob) {
                            console.log("Compressed file size (bytes):", blob.size);
                            console.log("Compressed file size (KB):", (blob.size / 1024).toFixed(2));
                            const compressedFile = new File([blob], file.name, {
                                type: file.type,
                                lastModified: Date.now()
                            });

                            // Replace the input file with compressed file
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(compressedFile);
                            imageInput.files = dataTransfer.files;

                            // Show preview
                            imagePreview.src = URL.createObjectURL(blob);
                            imagePreview.classList.remove("d-none");
                        },
                        file.type,
                        0.75 // 75% quality
                    );
                };
            };
            reader.readAsDataURL(file);
        });
    }

    //Keywords Input
    const enInput = document.getElementById("keyword_en_input");
    const heInput = document.getElementById("keyword_he_input");
    const container = document.getElementById("selectedKeywords");
    const hiddenInput = document.getElementById("keywords");

    let keywords = hiddenInput.value ? JSON.parse(hiddenInput.value) : [];

    // Add when pressing Enter in Hebrew or English field
    [enInput, heInput].forEach(input => {
        if (input) {
            input.addEventListener("keypress", e => {
                if (e.key === "Enter") {
                    e.preventDefault();
                    addKeyword(enInput.value.trim(), heInput.value.trim());
                    enInput.value = "";
                    heInput.value = "";
                }
            });
        }
    });

    // Add keyword pair
    function addKeyword(en, he) {
        if (!en || !he) return; // require both

        // prevent duplicate pair
        if (keywords.some(k => k.en === en && k.he === he)) return;

        keywords.push({ en, he });
        updateKeywords();
    }

    // Remove keyword pair
    function removeKeyword(index) {
        keywords.splice(index, 1);
        updateKeywords();
    }

    // Render badges + sync hidden input
    function updateKeywords() {
        container.innerHTML = "";
        keywords.forEach((kw, index) => {
            const badge = document.createElement("span");
            badge.className = "badge bg-primary me-1 mb-1 d-flex align-items-center";
            badge.textContent = `${kw.en || "—"} / ${kw.he || "—"}`;

            const removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className = "btn-close btn-close-white btn-sm ms-2";
            removeBtn.style.fontSize = "0.7em";
            removeBtn.addEventListener("click", () => removeKeyword(index));

            badge.appendChild(removeBtn);
            container.appendChild(badge);
        });

        hiddenInput.value = JSON.stringify(keywords);
    }

    // Preload if editing
    updateKeywords();
});
