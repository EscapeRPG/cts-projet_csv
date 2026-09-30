function initUserPermissions() {
    document.querySelectorAll('[data-choice-group]').forEach((group) => {
        if (group.dataset.permissionsInit === '1') return;

        // Same hierarchy as security.yaml: ADD implies EDIT, which implies VIEW.
        const view = group.querySelector('input[type="checkbox"][value$="_VIEW"]');
        const edit = group.querySelector('input[type="checkbox"][value$="_EDIT"]');
        const add = group.querySelector('input[type="checkbox"][value$="_ADD"]');
        if (!view || !edit || !add) return;

        group.dataset.permissionsInit = '1';
        const permissions = [view, edit, add];

        const checkInheritedPermissions = () => {
            if (add.checked) edit.checked = true;
            if (edit.checked) view.checked = true;
        };

        permissions.forEach((checkbox, index) => {
            checkbox.addEventListener('change', () => {
                if (!checkbox.checked) {
                    // Removing a prerequisite also removes the permissions that require it.
                    permissions.slice(index + 1).forEach((dependent) => {
                        dependent.checked = false;
                    });
                }
                checkInheritedPermissions();
            });
        });

        checkInheritedPermissions();
    });
}

document.addEventListener('DOMContentLoaded', initUserPermissions);
document.addEventListener('turbo:load', initUserPermissions);
