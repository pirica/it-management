/**
 * Shared utility for handling drag-and-drop file uploads.
 */
var itmUploadHelper = (function() {
    /**
     * Set up drag-and-drop for a specific target and its associated file input.
     * @param {HTMLElement} uploadTarget
     * @param {HTMLInputElement} fileInput
     */
    function setup(uploadTarget, fileInput) {
        if (!uploadTarget || !fileInput) return;

        uploadTarget.addEventListener("dragover", function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadTarget.classList.add("is-dragover");
        });

        uploadTarget.addEventListener("dragleave", function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadTarget.classList.remove("is-dragover");
        });

        uploadTarget.addEventListener("drop", function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadTarget.classList.remove("is-dragover");
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                if (fileInput.multiple) {
                    var dt = new DataTransfer();
                    if (fileInput.files) {
                        for (var i = 0; i < fileInput.files.length; i++) {
                            dt.items.add(fileInput.files[i]);
                        }
                    }
                    for (var j = 0; j < e.dataTransfer.files.length; j++) {
                        dt.items.add(e.dataTransfer.files[j]);
                    }
                    fileInput.files = dt.files;
                } else {
                    fileInput.files = e.dataTransfer.files;
                }
                fileInput.dispatchEvent(new Event("change", { bubbles: true }));
            }
        });

        uploadTarget.addEventListener("click", function(e) {
            if (e.target === fileInput) {
                return;
            }
            // Why: <label for="input"> already opens the picker; bubbling would double-open.
            var label = e.target.closest("label");
            if (label) {
                var isAssociated = false;
                if (fileInput.id && label.htmlFor === fileInput.id) {
                    isAssociated = true;
                } else if (label.control === fileInput) {
                    isAssociated = true;
                } else if (label.contains(fileInput)) {
                    isAssociated = true;
                } else if (fileInput.labels) {
                    for (var k = 0; k < fileInput.labels.length; k++) {
                        if (fileInput.labels[k] === label) {
                            isAssociated = true;
                            break;
                        }
                    }
                }
                if (isAssociated) {
                    return;
                }
            }
            fileInput.click();
        });

        uploadTarget.addEventListener("keydown", function(e) {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                fileInput.click();
            }
        });
    }

    return {
        /**
         * Initialize drag-and-drop using element IDs.
         */
        setupById: function(targetId, inputId) {
            var target = document.getElementById(targetId);
            var input = document.getElementById(inputId);
            setup(target, input);
        },

        /**
         * Initialize drag-and-drop for all elements matching a class.
         * Assumes each target contains exactly one file input.
         */
        setupByClass: function(className) {
            document.querySelectorAll(className).forEach(function(target) {
                var input = target.querySelector('input[type="file"]');
                setup(target, input);
            });
        }
    };
})();
