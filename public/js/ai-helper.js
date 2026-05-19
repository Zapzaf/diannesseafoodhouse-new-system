// AI Helper functionality
document.addEventListener('DOMContentLoaded', function() {
    const aiHelperBtn = document.getElementById('aiHelperBtn');
    const aiPrompt = document.getElementById('ai_prompt');
    const aiLoadingSpinner = document.getElementById('aiLoadingSpinner');
    const aiButtonText = document.getElementById('aiButtonText');
    const createExpressionForm = document.getElementById('createExpressionForm');
    
    // Keywords array to store selected keywords
    let selectedKeywords = [];
    
    // Form fields
    const formFields = {
        expression_name_he: document.getElementById('expression_name_he'),
        expression_name_en: document.getElementById('expression_name_en'),
        definition_he: document.getElementById('definition_he'),
        definition_en: document.getElementById('definition_en'),
        expression_example_he: document.getElementById('expression_example_he'),
        expression_example_en: document.getElementById('expression_example_en'),
        slug: document.getElementById('slug'),
        image_prompt: document.getElementById('image_prompt')
    };

    // Prevent multiple rapid requests
    let isGenerating = false;
    let lastRequestTime = 0;
    const MIN_REQUEST_INTERVAL = 3000; // 3 seconds between requests

    // Clear all form fields
    function clearFormFields() {
        Object.values(formFields).forEach(field => {
            if (field) {
                field.value = '';
            }
        });
        
        // Clear keywords
        selectedKeywords = [];
        renderKeywords();
        
        // Clear image preview
        const imagePreview = document.getElementById('imagePreview');
        if (imagePreview) {
            imagePreview.src = '';
            imagePreview.classList.add('d-none');
        }
    }

    // Disable/Enable form
    function toggleFormState(disable) {
        const formElements = createExpressionForm.querySelectorAll('input, textarea, button[type="submit"]');
        formElements.forEach(element => {
            if (disable) {
                element.disabled = true;
                element.style.opacity = '0.6';
            } else {
                element.disabled = false;
                element.style.opacity = '1';
            }
        });
        
        // Keep keyword inputs and file input enabled for manual editing after generation
        const manualInputs = [
            document.getElementById('keyword_he_input'),
            document.getElementById('keyword_en_input'),
            document.getElementById('image_url')
        ];
        
        if (!disable) {
            manualInputs.forEach(input => {
                if (input) {
                    input.disabled = false;
                    input.style.opacity = '1';
                }
            });
        }
    }

    // AI Helper Button Click
    aiHelperBtn.addEventListener('click', async function() {
        const hebrewExpression = aiPrompt.value.trim();
        
        if (!hebrewExpression) {
            alert('Please enter a Hebrew expression first!');
            return;
        }

        // Prevent rapid successive requests
        const now = Date.now();
        if (isGenerating) {
            showNotification('Please wait, AI is still generating...', 'error');
            return;
        }
        
        if (now - lastRequestTime < MIN_REQUEST_INTERVAL) {
            const waitTime = Math.ceil((MIN_REQUEST_INTERVAL - (now - lastRequestTime)) / 1000);
            showNotification(`Please wait ${waitTime} more seconds before trying again.`, 'error');
            return;
        }

        // Clear previous entries before generating new ones
        clearFormFields();

        // Show loading state and disable form
        isGenerating = true;
        lastRequestTime = now;
        aiHelperBtn.disabled = true;
        aiLoadingSpinner.classList.remove('d-none');
        aiButtonText.textContent = 'Generating...';
        toggleFormState(true); // Disable form during generation

        try {
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                           || document.querySelector('input[name="_token"]')?.value;

            // Make API request
            const response = await fetch('/api/ai-generate-expression', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    hebrew_expression: hebrewExpression
                })
            });

            // Check if response is ok
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server response:', errorText);
                throw new Error(`Server error: ${response.status}`);
            }

            // Try to parse JSON
            let result;
            try {
                result = await response.json();
            } catch (jsonError) {
                console.error('Failed to parse JSON:', jsonError);
                const text = await response.text();
                console.error('Response text:', text.substring(0, 500));
                throw new Error('Server returned invalid response format');
            }

            if (result.success && result.data) {
                // Fill form fields with AI-generated content
                fillFormFields(result.data);
                
                // Enable form after successful generation
                toggleFormState(false);
                
                // Show success message
                showNotification('Form filled successfully! Review and adjust as needed.', 'success');
            } else {
                throw new Error(result.message || 'Failed to generate content');
            }

        } catch (error) {
            console.error('AI Helper Error:', error);
            
            // Enable form on error
            toggleFormState(false);
            
            // Check if it's a rate limit error
            if (error.message && error.message.includes('rate limit')) {
                showNotification('OpenAI rate limit reached. Please wait a moment and try again.', 'error');
            } else if (error.message && error.message.includes('429')) {
                showNotification('Too many requests. Please wait 60 seconds before trying again.', 'error');
            } else {
                showNotification('Failed to generate content. Please try again.', 'error');
            }
        } finally {
            // Reset loading state
            isGenerating = false;
            aiHelperBtn.disabled = false;
            aiLoadingSpinner.classList.add('d-none');
            aiButtonText.textContent = 'Generate with AI';
        }
    });

    // Fill form fields with AI data
    function fillFormFields(data) {
        // Fill basic fields with fallback to dash if empty
        formFields.expression_name_he.value = data.expression_name_he || '-';
        formFields.expression_name_en.value = data.expression_name_en || '-';
        formFields.definition_he.value = data.definition_he || '-';
        formFields.definition_en.value = data.definition_en || '-';
        
        // Handle example fields (both possible field names)
        if (data.example_he) {
            formFields.expression_example_he.value = data.example_he;
        } else if (data.expression_example_he) {
            formFields.expression_example_he.value = data.expression_example_he;
        } else {
            formFields.expression_example_he.value = '-';
        }
        
        if (data.example_en) {
            formFields.expression_example_en.value = data.example_en;
        } else if (data.expression_example_en) {
            formFields.expression_example_en.value = data.expression_example_en;
        } else {
            formFields.expression_example_en.value = '-';
        }
        
        formFields.slug.value = data.slug || '-';
        
        // Fill image prompt
        formFields.image_prompt.value = data.image_prompt || '-';

        // Handle keywords
        if (data.keywords && Array.isArray(data.keywords) && data.keywords.length > 0) {
            selectedKeywords = data.keywords;
            renderKeywords();
        } else {
            selectedKeywords = [];
            renderKeywords();
        }

        // Smooth scroll to form
        createExpressionForm.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }

    // Render keywords as badges
    function renderKeywords() {
        const container = document.getElementById('selectedKeywords');
        const hiddenInput = document.getElementById('keywords');
        
        container.innerHTML = '';
        
        if (selectedKeywords.length === 0) {
            hiddenInput.value = '[]';
            return;
        }
        
        selectedKeywords.forEach((keyword, index) => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary d-flex align-items-center gap-2';
            badge.innerHTML = `
                <span>${keyword.he || '-'} / ${keyword.en || '-'}</span>
                <button type="button" class="btn-close btn-close-white btn-sm" 
                        onclick="removeKeyword(${index})" style="font-size: 0.6rem;"></button>
            `;
            container.appendChild(badge);
        });

        // Update hidden input
        hiddenInput.value = JSON.stringify(selectedKeywords);
    }

    // Manual keyword addition
    const keywordHeInput = document.getElementById('keyword_he_input');
    const keywordEnInput = document.getElementById('keyword_en_input');

    function addKeyword() {
        const heKeyword = keywordHeInput.value.trim();
        const enKeyword = keywordEnInput.value.trim();

        if (heKeyword && enKeyword) {
            selectedKeywords.push({ he: heKeyword, en: enKeyword });
            renderKeywords();
            
            // Clear inputs
            keywordHeInput.value = '';
            keywordEnInput.value = '';
        }
    }

    // Add keyword on Enter key
    [keywordHeInput, keywordEnInput].forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addKeyword();
            }
        });
    });

    // Remove keyword function (global scope)
    window.removeKeyword = function(index) {
        selectedKeywords.splice(index, 1);
        renderKeywords();
    };

    // Show notification
    function showNotification(message, type = 'success') {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        const container = document.querySelector('.container-xl.px-4.mt-n10');
        container.insertAdjacentHTML('afterbegin', alertHtml);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 5000);
    }

    // Image preview functionality
    const imageInput = document.getElementById('image_url');
    const imagePreview = document.getElementById('imagePreview');

    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Copy image prompt button functionality
    const copyImagePromptBtn = document.getElementById('copyImagePrompt');
    const imagePromptField = document.getElementById('image_prompt');
    
    if (copyImagePromptBtn && imagePromptField) {
        copyImagePromptBtn.addEventListener('click', function() {
            const promptText = imagePromptField.value;
            
            if (!promptText || promptText === '-') {
                showNotification('No image prompt to copy yet!', 'error');
                return;
            }
            
            // Copy to clipboard
            navigator.clipboard.writeText(promptText).then(function() {
                showNotification('Image prompt copied to clipboard!', 'success');
                
                // Change button text temporarily
                const originalHTML = copyImagePromptBtn.innerHTML;
                copyImagePromptBtn.innerHTML = '<i class="me-1" data-feather="check"></i>Copied!';
                
                setTimeout(() => {
                    copyImagePromptBtn.innerHTML = originalHTML;
                    feather.replace(); // Re-initialize feather icons
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy:', err);
                showNotification('Failed to copy. Please copy manually.', 'error');
            });
        });
    }
});