// 2. Dynamic Pickup Slots Setup
const addSlotButton = document.getElementById('addSlotButton');
const slotsContainer = document.getElementById('slots');
const noSlotsText = document.getElementById('noSlotsText');
const form = document.getElementById('publish-donation-form');

addSlotButton.addEventListener('click', function () {
    const currentSlots = slotsContainer.querySelectorAll('.slot-row').length;

    if (currentSlots >= 3) {
        alert("You can add a maximum of 3 pickup slots.");
        return;
    }

    noSlotsText.style.display = 'none';

    const slotRow = document.createElement('div');
    slotRow.className = 'slot-row';

    slotRow.innerHTML = `
        <input type="datetime-local" name="pickup_slots[]" required>
        <button type="button" class="remove-slot-button" title="Remove slot">&times;</button>
    `;

    slotRow.querySelector('.remove-slot-button').addEventListener('click', function () {
        slotRow.remove();

        if (slotsContainer.querySelectorAll('.slot-row').length === 0) {
            noSlotsText.style.display = 'block';
        }
    });

    slotsContainer.appendChild(slotRow);
});


// 3. Form Validation
form.addEventListener('submit', function (event) {

    // Allergy validation
    const checkedAllergies = document.querySelectorAll(
        'input[name="allergies"]:checked'
    );

    if (checkedAllergies.length === 0) {
        alert("Please select at least one Allergy Tag.");
        return;
    }

    // 'None' cannot be selected with others
    const noneCheckbox = document.getElementById('none');

    if (noneCheckbox.checked && checkedAllergies.length > 1) {
        alert("'None' cannot be selected together with other allergy tags.");
        return;
    }

    // Quantity validation
    const quantity = document.getElementById('quantity');

    if (parseInt(quantity.value) <= 0) {
        alert("Quantity must be greater than 0.");
        quantity.focus();
        return;
    }

    // Pickup slot validation
    const pickupSlots = document.querySelectorAll(
        'input[name="pickup_slots[]"]'
    );

    if (pickupSlots.length === 0) {
        alert("Please add at least one Pickup Slot.");
        return;
    }

    // Expiry date validation
    const expiryDate = document.getElementById('expiryDate');
    const expiry = new Date(expiryDate.value);

    if (expiry <= new Date()) {
        alert("Expiry date must be in the future.");
        expiryDate.focus();
        return;
    }

    // Pickup slots must be before expiry
    for (const slot of pickupSlots) {
        const slotTime = new Date(slot.value);

        if (slotTime > expiry) {
            alert("Pickup slots cannot be after the food expiry date.");
            slot.focus();
            return;
        }
    }
});