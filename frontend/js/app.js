import {
    addRecipient,
    ApiError,
    checkAdmin,
    checkSession,
    createAdminAccount,
    createAdminMovement,
    createAdminUser,
    createDeposit,
    createTransfer,
    deleteAdminAccount,
    deleteAdminMovement,
    deleteAdminUser,
    deleteRecipient,
    getAccount,
    getAdminAccount,
    getAdminAccounts,
    getAdminMovement,
    getAdminMovements,
    getAdminUser,
    getAdminUsers,
    getMovements,
    getProfile,
    getRecipients,
    getToken,
    loginUser,
    profileImageUrl,
    registerUser,
    removeToken,
    saveToken,
    simulateFixedTerm,
    updateAdminAccount,
    updateAdminMovement,
    updateAdminUser,
    updateProfile,
} from './api.js';

const state = {
    user: null,
    account: null,
    movementPage: 1,
    movementLastPage: 1,
    isAdmin: false,
    adminPages: {
        users: { current: 1, last: 1 },
        accounts: { current: 1, last: 1 },
        movements: { current: 1, last: 1 },
    },
    adminMovementFilters: { accountId: '', userId: '' },
    pendingRequests: 0,
};

const elements = {
    authView: document.querySelector('#auth-view'),
    appView: document.querySelector('#app-view'),
    logoutButton: document.querySelector('#logout-button'),
    message: document.querySelector('#message'),
    loading: document.querySelector('#loading'),
    loginForm: document.querySelector('#login-form'),
    registerForm: document.querySelector('#register-form'),
    depositForm: document.querySelector('#deposit-form'),
    transferForm: document.querySelector('#transfer-form'),
    movementsBody: document.querySelector('#movements-body'),
    recipientForm: document.querySelector('#recipient-form'),
    recipientsList: document.querySelector('#recipients-list'),
    profileForm: document.querySelector('#profile-form'),
    profileImage: document.querySelector('#profile-image'),
    fixedTermForm: document.querySelector('#fixed-term-form'),
    fixedTermResult: document.querySelector('#fixed-term-result'),
    adminNavButton: document.querySelector('#admin-nav-button'),
    adminUsersBody: document.querySelector('#admin-users-body'),
    adminAccountsBody: document.querySelector('#admin-accounts-body'),
    adminMovementsBody: document.querySelector('#admin-movements-body'),
    adminMovementFilters: document.querySelector('#admin-movement-filters'),
    adminUserDialog: document.querySelector('#admin-user-dialog'),
    adminAccountDialog: document.querySelector('#admin-account-dialog'),
    adminMovementDialog: document.querySelector('#admin-movement-dialog'),
    adminUserForm: document.querySelector('#admin-user-form'),
    adminAccountForm: document.querySelector('#admin-account-form'),
    adminMovementForm: document.querySelector('#admin-movement-form'),
};

function setLoading(isLoading) {
    state.pendingRequests += isLoading ? 1 : -1;
    state.pendingRequests = Math.max(0, state.pendingRequests);
    elements.loading.hidden = state.pendingRequests === 0;
}

async function withLoading(callback) {
    setLoading(true);

    try {
        return await callback();
    } finally {
        setLoading(false);
    }
}

function showMessage(text, type = 'success') {
    elements.message.textContent = text;
    elements.message.classList.toggle('error', type === 'error');
    elements.message.hidden = false;
    elements.message.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hideMessage() {
    elements.message.hidden = true;
    elements.message.textContent = '';
}

function showError(error) {
    const message = error instanceof ApiError || error instanceof Error
        ? error.message
        : 'Ocurrió un error inesperado.';

    showMessage(message, 'error');
}

function showAuthView(message = '') {
    state.user = null;
    state.account = null;
    hideAdministration();
    [elements.adminUserDialog, elements.adminAccountDialog, elements.adminMovementDialog].forEach((dialog) => {
        if (dialog.open) {
            dialog.close();
        }
    });
    elements.authView.hidden = false;
    elements.appView.hidden = true;
    elements.logoutButton.hidden = true;

    if (message) {
        showMessage(message, 'error');
    }
}

function showAppView() {
    elements.authView.hidden = true;
    elements.appView.hidden = false;
    elements.logoutButton.hidden = false;
}

function formValues(form) {
    return Object.fromEntries(new FormData(form).entries());
}

function formatMoney(value, currency = 'ARS') {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency,
    }).format(Number(value));
}

function formatDate(value) {
    return new Intl.DateTimeFormat('es-AR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function accountTypeLabel(type) {
    return type === 'checking' ? 'Cuenta corriente' : 'Caja de ahorro';
}

function movementTypeLabel(type) {
    const labels = {
        deposit: 'Depósito',
        transfer_out: 'Transferencia enviada',
        transfer_in: 'Transferencia recibida',
    };

    return labels[type] || type;
}

function appendCell(row, value, className = '') {
    const cell = document.createElement('td');
    cell.textContent = value;

    if (className) {
        cell.className = className;
    }

    row.append(cell);
}

function renderSummary() {
    document.querySelector('#summary-name').textContent = state.user.name;
    document.querySelector('#account-cbu').textContent = state.account.cbu;
    document.querySelector('#account-type').textContent = accountTypeLabel(state.account.type);
    document.querySelector('#account-currency').textContent = state.account.currency;
    document.querySelector('#account-balance').textContent = formatMoney(
        state.account.balance,
        state.account.currency,
    );
}

function renderProfile() {
    elements.profileForm.elements.name.value = state.user.name;
    elements.profileForm.elements.email.value = state.user.email;
    elements.profileForm.elements.age.value = state.user.age ?? '';

    const imageUrl = profileImageUrl(state.user.image);
    elements.profileImage.hidden = !imageUrl;

    if (imageUrl) {
        elements.profileImage.src = imageUrl;
    } else {
        elements.profileImage.removeAttribute('src');
    }
}

async function loadSummary() {
    const [profileResponse, accountResponse] = await Promise.all([
        getProfile(),
        getAccount(),
    ]);

    state.user = profileResponse.data;
    state.account = accountResponse.data;
    renderSummary();
    renderProfile();
}

function renderMovements(response) {
    elements.movementsBody.replaceChildren();

    if (response.data.length === 0) {
        const row = document.createElement('tr');
        appendCell(row, 'Todavía no hay movimientos.', 'empty-cell');
        row.firstElementChild.colSpan = 4;
        elements.movementsBody.append(row);
    } else {
        response.data.forEach((movement) => {
            const row = document.createElement('tr');
            appendCell(row, movementTypeLabel(movement.type));
            appendCell(row, formatMoney(movement.amount, state.account?.currency));
            appendCell(row, formatDate(movement.date));
            appendCell(row, movement.counterparty_cbu || '—');
            elements.movementsBody.append(row);
        });
    }

    state.movementPage = response.current_page;
    state.movementLastPage = response.last_page;
    document.querySelector('#page-information').textContent = `Página ${response.current_page} de ${response.last_page}`;
    document.querySelector('#previous-page-button').disabled = !response.prev_page_url;
    document.querySelector('#next-page-button').disabled = !response.next_page_url;
}

async function loadMovements(page = 1) {
    const response = await getMovements(page);
    renderMovements(response);
}

function createRecipientItem(recipient) {
    const item = document.createElement('article');
    item.className = 'list-item';

    const details = document.createElement('div');
    details.className = 'list-item-details';

    const holder = document.createElement('strong');
    holder.textContent = recipient.holder;

    const cbu = document.createElement('span');
    cbu.textContent = recipient.cbu;

    const actions = document.createElement('div');
    actions.className = 'list-actions';

    const useButton = document.createElement('button');
    useButton.type = 'button';
    useButton.className = 'button button-secondary';
    useButton.textContent = 'Transferir';
    useButton.dataset.action = 'use-recipient';
    useButton.dataset.cbu = recipient.cbu;

    const deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'button button-danger';
    deleteButton.textContent = 'Eliminar';
    deleteButton.dataset.action = 'delete-recipient';
    deleteButton.dataset.cbu = recipient.cbu;

    details.append(holder, cbu);
    actions.append(useButton, deleteButton);
    item.append(details, actions);

    return item;
}

function renderRecipients(recipients) {
    elements.recipientsList.replaceChildren();

    if (recipients.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'card muted';
        empty.textContent = 'Todavía no guardaste ningún CBU.';
        elements.recipientsList.append(empty);
        return;
    }

    recipients.forEach((recipient) => {
        elements.recipientsList.append(createRecipientItem(recipient));
    });
}

async function loadRecipients() {
    const response = await getRecipients(state.user.id);
    renderRecipients(response.data);
}

function hideAdministration() {
    state.isAdmin = false;
    elements.adminNavButton.hidden = true;

    if (document.querySelector('#admin-section').classList.contains('active')) {
        activateSection('summary-section');
    }
}

async function detectAdministrator() {
    try {
        await checkAdmin();
        state.isAdmin = true;
        elements.adminNavButton.hidden = false;
    } catch (error) {
        if (error instanceof ApiError && error.status === 403) {
            hideAdministration();
            return;
        }

        throw error;
    }
}

function handleAdminError(error) {
    if (error instanceof ApiError && error.status === 403) {
        hideAdministration();
    }

    showError(error);
}

function createActionButtons(resource, id) {
    const actions = document.createElement('div');
    actions.className = 'table-actions';

    const editButton = document.createElement('button');
    editButton.type = 'button';
    editButton.className = 'button button-secondary';
    editButton.textContent = 'Editar';
    editButton.dataset.adminAction = 'edit';
    editButton.dataset.resource = resource;
    editButton.dataset.id = id;

    const deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'button button-danger';
    deleteButton.textContent = 'Eliminar';
    deleteButton.dataset.adminAction = 'delete';
    deleteButton.dataset.resource = resource;
    deleteButton.dataset.id = id;

    actions.append(editButton, deleteButton);

    return actions;
}

function appendActionsCell(row, resource, id) {
    const cell = document.createElement('td');
    cell.append(createActionButtons(resource, id));
    row.append(cell);
}

function renderEmptyAdminTable(body, columns) {
    const row = document.createElement('tr');
    appendCell(row, 'No hay registros para mostrar.', 'empty-cell');
    row.firstElementChild.colSpan = columns;
    body.append(row);
}

function updateAdminPagination(resource, response) {
    state.adminPages[resource] = { current: response.current_page, last: response.last_page };
    document.querySelector(`#admin-${resource}-page`).textContent = `Página ${response.current_page} de ${response.last_page} · ${response.total} registros`;

    const previous = document.querySelector(`[data-admin-resource="${resource}"][data-page-direction="previous"]`);
    const next = document.querySelector(`[data-admin-resource="${resource}"][data-page-direction="next"]`);
    previous.disabled = !response.prev_page_url;
    next.disabled = !response.next_page_url;
}

function renderAdminUsers(response) {
    elements.adminUsersBody.replaceChildren();

    if (!response.data.length) {
        renderEmptyAdminTable(elements.adminUsersBody, 6);
    }

    response.data.forEach((user) => {
        const row = document.createElement('tr');
        appendCell(row, user.id);
        appendCell(row, user.name);
        appendCell(row, user.email);
        appendCell(row, user.age ?? '—');
        appendCell(row, user.role);
        appendActionsCell(row, 'users', user.id);
        elements.adminUsersBody.append(row);
    });

    updateAdminPagination('users', response);
}

function renderAdminAccounts(response) {
    elements.adminAccountsBody.replaceChildren();

    if (!response.data.length) {
        renderEmptyAdminTable(elements.adminAccountsBody, 7);
    }

    response.data.forEach((account) => {
        const row = document.createElement('tr');
        appendCell(row, account.id);
        appendCell(row, `${account.user?.name || 'Sin usuario'} (#${account.user_id})`);
        appendCell(row, account.cbu);
        appendCell(row, accountTypeLabel(account.type));
        appendCell(row, account.currency);
        appendCell(row, formatMoney(account.balance, account.currency));
        appendActionsCell(row, 'accounts', account.id);
        elements.adminAccountsBody.append(row);
    });

    updateAdminPagination('accounts', response);
}

function renderAdminMovements(response) {
    elements.adminMovementsBody.replaceChildren();

    if (!response.data.length) {
        renderEmptyAdminTable(elements.adminMovementsBody, 8);
    }

    response.data.forEach((movement) => {
        const row = document.createElement('tr');
        appendCell(row, movement.id);
        appendCell(row, `${movement.account?.cbu || '—'} (#${movement.account_id})`);
        appendCell(row, movement.account?.user_id ? `#${movement.account.user_id}` : '—');
        appendCell(row, movementTypeLabel(movement.type));
        appendCell(row, formatMoney(movement.amount, movement.account?.currency || 'ARS'));
        appendCell(row, movement.counterparty_cbu || '—');
        appendCell(row, formatDate(movement.created_at));
        appendActionsCell(row, 'movements', movement.id);
        elements.adminMovementsBody.append(row);
    });

    updateAdminPagination('movements', response);
}

async function loadAdminUsers(page = 1) {
    renderAdminUsers(await getAdminUsers({ page }));
}

async function loadAdminAccounts(page = 1) {
    renderAdminAccounts(await getAdminAccounts({ page }));
}

async function loadAdminMovements(page = 1) {
    renderAdminMovements(await getAdminMovements({
        page,
        accountId: state.adminMovementFilters.accountId,
        userId: state.adminMovementFilters.userId,
    }));
}

const adminLoaders = {
    users: loadAdminUsers,
    accounts: loadAdminAccounts,
    movements: loadAdminMovements,
};

async function loadAdministration() {
    await Promise.all([loadAdminUsers(), loadAdminAccounts(), loadAdminMovements()]);
}

async function loadApplication() {
    await loadSummary();
    await Promise.all([loadMovements(), loadRecipients(), detectAdministrator()]);
}

function activateSection(sectionId) {
    document.querySelectorAll('.app-section').forEach((section) => {
        section.classList.toggle('active', section.id === sectionId);
    });

    document.querySelectorAll('.nav-button').forEach((button) => {
        button.classList.toggle('active', button.dataset.section === sectionId);
    });
}

function activateAdminPanel(panelId) {
    document.querySelectorAll('.admin-panel').forEach((panel) => {
        panel.classList.toggle('active', panel.id === panelId);
    });

    document.querySelectorAll('.admin-tab').forEach((button) => {
        button.classList.toggle('active', button.dataset.adminPanel === panelId);
    });
}

function openCreateUserDialog() {
    elements.adminUserForm.reset();
    delete elements.adminUserForm.dataset.resourceId;
    document.querySelector('#admin-user-dialog-title').textContent = 'Crear usuario';
    elements.adminUserForm.elements.password.required = true;
    document.querySelector('#admin-user-password-help').hidden = true;
    elements.adminUserDialog.showModal();
}

async function openEditUserDialog(userId) {
    const response = await getAdminUser(userId);
    const user = response.data;
    elements.adminUserForm.reset();
    elements.adminUserForm.dataset.resourceId = user.id;
    elements.adminUserForm.elements.name.value = user.name;
    elements.adminUserForm.elements.email.value = user.email;
    elements.adminUserForm.elements.age.value = user.age ?? '';
    elements.adminUserForm.elements.role.value = user.role;
    elements.adminUserForm.elements.password.required = false;
    document.querySelector('#admin-user-password-help').hidden = false;
    document.querySelector('#admin-user-dialog-title').textContent = `Editar usuario #${user.id}`;
    elements.adminUserDialog.showModal();
}

function openCreateAccountDialog() {
    elements.adminAccountForm.reset();
    delete elements.adminAccountForm.dataset.resourceId;
    document.querySelector('#admin-account-dialog-title').textContent = 'Crear cuenta';
    document.querySelector('#admin-account-user-field').hidden = false;
    elements.adminAccountForm.elements.user_id.disabled = false;
    elements.adminAccountDialog.showModal();
}

async function openEditAccountDialog(accountId) {
    const response = await getAdminAccount(accountId);
    const account = response.data;
    elements.adminAccountForm.reset();
    elements.adminAccountForm.dataset.resourceId = account.id;
    elements.adminAccountForm.elements.cbu.value = account.cbu;
    elements.adminAccountForm.elements.type.value = account.type;
    elements.adminAccountForm.elements.currency.value = account.currency;
    elements.adminAccountForm.elements.balance.value = account.balance;
    document.querySelector('#admin-account-user-field').hidden = true;
    elements.adminAccountForm.elements.user_id.disabled = true;
    document.querySelector('#admin-account-dialog-title').textContent = `Editar cuenta #${account.id}`;
    elements.adminAccountDialog.showModal();
}

function updateCounterpartyRequirement() {
    const type = elements.adminMovementForm.elements.type.value;
    elements.adminMovementForm.elements.counterparty_cbu.required = type === 'transfer_out' || type === 'transfer_in';
}

function openCreateMovementDialog() {
    elements.adminMovementForm.reset();
    delete elements.adminMovementForm.dataset.resourceId;
    document.querySelector('#admin-movement-dialog-title').textContent = 'Crear movimiento';
    updateCounterpartyRequirement();
    elements.adminMovementDialog.showModal();
}

async function openEditMovementDialog(movementId) {
    const response = await getAdminMovement(movementId);
    const movement = response.data;
    elements.adminMovementForm.reset();
    elements.adminMovementForm.dataset.resourceId = movement.id;
    elements.adminMovementForm.elements.account_id.value = movement.account_id;
    elements.adminMovementForm.elements.type.value = movement.type;
    elements.adminMovementForm.elements.amount.value = movement.amount;
    elements.adminMovementForm.elements.counterparty_cbu.value = movement.counterparty_cbu ?? '';
    updateCounterpartyRequirement();
    document.querySelector('#admin-movement-dialog-title').textContent = `Editar movimiento #${movement.id}`;
    elements.adminMovementDialog.showModal();
}

function adminUserPayload(form) {
    const fields = formValues(form);
    fields.age = fields.age === '' ? null : fields.age;

    if (fields.password === '') {
        delete fields.password;
    }

    return fields;
}

function adminMovementPayload(form) {
    const fields = formValues(form);
    fields.counterparty_cbu = fields.counterparty_cbu || null;

    return fields;
}

elements.loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    try {
        const response = await withLoading(() => loginUser(formValues(elements.loginForm)));
        saveToken(response.data.access_token);
        await withLoading(loadApplication);
        elements.loginForm.reset();
        showAppView();
        showMessage('Sesión iniciada correctamente.');
    } catch (error) {
        removeToken();
        showAuthView();
        showError(error);
    }
});

elements.registerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    try {
        const fields = formValues(elements.registerForm);
        await withLoading(() => registerUser(fields));
        elements.loginForm.elements.email.value = fields.email;
        elements.registerForm.reset();
        showMessage('Cuenta creada correctamente. Ya podés iniciar sesión.');
        elements.loginForm.elements.password.focus();
    } catch (error) {
        showError(error);
    }
});

elements.logoutButton.addEventListener('click', () => {
    removeToken();
    hideMessage();
    showAuthView();
    showMessage('La sesión se cerró en este navegador.');
});

document.querySelectorAll('.nav-button').forEach((button) => {
    button.addEventListener('click', async () => {
        activateSection(button.dataset.section);

        if (button.dataset.section === 'admin-section' && state.isAdmin) {
            hideMessage();

            try {
                await withLoading(loadAdministration);
            } catch (error) {
                handleAdminError(error);
            }
        }
    });
});

document.querySelector('#refresh-summary-button').addEventListener('click', async () => {
    hideMessage();

    try {
        await withLoading(loadSummary);
        showMessage('Datos actualizados.');
    } catch (error) {
        showError(error);
    }
});

elements.depositForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    try {
        const { amount } = formValues(elements.depositForm);
        await withLoading(() => createDeposit(amount));
        elements.depositForm.reset();
        await withLoading(() => Promise.all([loadSummary(), loadMovements(1)]));
        showMessage('Depósito realizado correctamente.');
    } catch (error) {
        showError(error);
    }
});

elements.transferForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    try {
        const fields = formValues(elements.transferForm);
        const response = await withLoading(() => createTransfer(fields.destination_cbu, fields.amount));
        elements.transferForm.reset();
        await withLoading(() => Promise.all([loadSummary(), loadMovements(1)]));
        showMessage(response.data.message);
    } catch (error) {
        showError(error);
    }
});

document.querySelector('#refresh-movements-button').addEventListener('click', async () => {
    hideMessage();

    try {
        await withLoading(() => loadMovements(state.movementPage));
        showMessage('Movimientos actualizados.');
    } catch (error) {
        showError(error);
    }
});

document.querySelector('#previous-page-button').addEventListener('click', async () => {
    if (state.movementPage <= 1) {
        return;
    }

    try {
        await withLoading(() => loadMovements(state.movementPage - 1));
    } catch (error) {
        showError(error);
    }
});

document.querySelector('#next-page-button').addEventListener('click', async () => {
    if (state.movementPage >= state.movementLastPage) {
        return;
    }

    try {
        await withLoading(() => loadMovements(state.movementPage + 1));
    } catch (error) {
        showError(error);
    }
});

elements.recipientForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    try {
        const { cbu } = formValues(elements.recipientForm);
        await withLoading(() => addRecipient(cbu, state.user.id));
        elements.recipientForm.reset();
        await withLoading(loadRecipients);
        showMessage('CBU guardado correctamente.');
    } catch (error) {
        showError(error);
    }
});

elements.recipientsList.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');

    if (!button) {
        return;
    }

    if (button.dataset.action === 'use-recipient') {
        document.querySelector('#transfer-cbu').value = button.dataset.cbu;
        activateSection('operations-section');
        document.querySelector('#transfer-cbu').focus();
        return;
    }

    if (button.dataset.action === 'delete-recipient') {
        hideMessage();

        try {
            const response = await withLoading(() => deleteRecipient(button.dataset.cbu, state.user.id));
            await withLoading(loadRecipients);
            showMessage(response.message);
        } catch (error) {
            showError(error);
        }
    }
});

elements.profileForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    try {
        const profileData = new FormData(elements.profileForm);

        if (!elements.profileForm.elements.image.files.length) {
            profileData.delete('image');
        }

        const response = await withLoading(() => updateProfile(profileData));
        state.user = response.data;
        elements.profileForm.elements.image.value = '';
        renderSummary();
        renderProfile();
        showMessage('Perfil actualizado correctamente.');
    } catch (error) {
        showError(error);
    }
});

elements.fixedTermForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    try {
        const fields = formValues(elements.fixedTermForm);
        const response = await withLoading(() => simulateFixedTerm(fields.amount, fields.term_days));
        const result = response.data;
        document.querySelector('#fixed-term-created-at').textContent = result.created_at;
        document.querySelector('#fixed-term-ends-at').textContent = result.ends_at;
        document.querySelector('#fixed-term-invested').textContent = formatMoney(result.invested_amount, state.account.currency);
        document.querySelector('#fixed-term-interest').textContent = formatMoney(result.earned_interest, state.account.currency);
        document.querySelector('#fixed-term-total').textContent = formatMoney(result.total, state.account.currency);
        elements.fixedTermResult.hidden = false;
        showMessage('Simulación realizada. No se modificó el saldo de la cuenta.');
    } catch (error) {
        showError(error);
    }
});

document.querySelectorAll('.admin-tab').forEach((button) => {
    button.addEventListener('click', () => activateAdminPanel(button.dataset.adminPanel));
});

document.querySelector('#create-admin-user-button').addEventListener('click', openCreateUserDialog);
document.querySelector('#create-admin-account-button').addEventListener('click', openCreateAccountDialog);
document.querySelector('#create-admin-movement-button').addEventListener('click', openCreateMovementDialog);

document.querySelectorAll('[data-close-dialog]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog').close());
});

elements.adminMovementForm.elements.type.addEventListener('change', updateCounterpartyRequirement);

elements.adminUserForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    try {
        const userId = elements.adminUserForm.dataset.resourceId;
        const payload = adminUserPayload(elements.adminUserForm);

        if (userId) {
            await withLoading(() => updateAdminUser(userId, payload));
        } else {
            await withLoading(() => createAdminUser(payload));
        }

        elements.adminUserDialog.close();
        await withLoading(() => loadAdminUsers(state.adminPages.users.current));
        showMessage(userId ? 'Usuario actualizado correctamente.' : 'Usuario creado con su cuenta vacía.');
    } catch (error) {
        handleAdminError(error);
    }
});

elements.adminAccountForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    try {
        const accountId = elements.adminAccountForm.dataset.resourceId;
        const payload = formValues(elements.adminAccountForm);

        if (accountId) {
            delete payload.user_id;
            await withLoading(() => updateAdminAccount(accountId, payload));
        } else {
            await withLoading(() => createAdminAccount(payload));
        }

        elements.adminAccountDialog.close();
        await withLoading(() => loadAdminAccounts(state.adminPages.accounts.current));
        showMessage(accountId ? 'Cuenta actualizada. El cambio de saldo no generó movimientos.' : 'Cuenta creada correctamente.');
    } catch (error) {
        handleAdminError(error);
    }
});

elements.adminMovementForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    try {
        const movementId = elements.adminMovementForm.dataset.resourceId;
        const payload = adminMovementPayload(elements.adminMovementForm);

        if (movementId) {
            await withLoading(() => updateAdminMovement(movementId, payload));
        } else {
            await withLoading(() => createAdminMovement(payload));
        }

        elements.adminMovementDialog.close();
        await withLoading(() => loadAdminMovements(state.adminPages.movements.current));
        showMessage(`Movimiento ${movementId ? 'actualizado' : 'creado'}. El saldo de la cuenta no fue recalculado.`);
    } catch (error) {
        handleAdminError(error);
    }
});

document.querySelector('#admin-section').addEventListener('click', async (event) => {
    const actionButton = event.target.closest('button[data-admin-action]');

    if (!actionButton) {
        return;
    }

    const { adminAction, resource, id } = actionButton.dataset;
    hideMessage();

    try {
        if (adminAction === 'edit') {
            const editors = {
                users: openEditUserDialog,
                accounts: openEditAccountDialog,
                movements: openEditMovementDialog,
            };
            await withLoading(() => editors[resource](id));
            return;
        }

        const warning = resource === 'users'
            ? '¿Eliminar este usuario? También se eliminarán su cuenta, movimientos y datos asociados de Wallet.'
            : resource === 'accounts'
                ? '¿Eliminar esta cuenta y sus datos asociados?'
                : '¿Eliminar este movimiento? El saldo de la cuenta no se recalculará.';

        if (!window.confirm(warning)) {
            return;
        }

        const deleters = {
            users: deleteAdminUser,
            accounts: deleteAdminAccount,
            movements: deleteAdminMovement,
        };
        await withLoading(() => deleters[resource](id));
        await withLoading(() => adminLoaders[resource](state.adminPages[resource].current));
        showMessage('Registro eliminado correctamente.');
    } catch (error) {
        handleAdminError(error);
    }
});

document.querySelectorAll('[data-admin-resource][data-page-direction]').forEach((button) => {
    button.addEventListener('click', async () => {
        const resource = button.dataset.adminResource;
        const pagination = state.adminPages[resource];
        const targetPage = button.dataset.pageDirection === 'next'
            ? pagination.current + 1
            : pagination.current - 1;

        if (targetPage < 1 || targetPage > pagination.last) {
            return;
        }

        try {
            await withLoading(() => adminLoaders[resource](targetPage));
        } catch (error) {
            handleAdminError(error);
        }
    });
});

elements.adminMovementFilters.addEventListener('submit', async (event) => {
    event.preventDefault();
    const filters = formValues(elements.adminMovementFilters);
    state.adminMovementFilters.accountId = filters.account_id;
    state.adminMovementFilters.userId = filters.user_id;

    try {
        await withLoading(() => loadAdminMovements(1));
    } catch (error) {
        handleAdminError(error);
    }
});

document.querySelector('#clear-admin-movement-filters').addEventListener('click', async () => {
    elements.adminMovementFilters.reset();
    state.adminMovementFilters = { accountId: '', userId: '' };

    try {
        await withLoading(() => loadAdminMovements(1));
    } catch (error) {
        handleAdminError(error);
    }
});

window.addEventListener('wallet:unauthorized', () => {
    showAuthView('La sesión venció o dejó de ser válida. Ingresá nuevamente.');
});

async function startApplication() {
    if (!getToken()) {
        showAuthView();
        return;
    }

    try {
        await withLoading(checkSession);
        await withLoading(loadApplication);
        showAppView();
    } catch (error) {
        if (error.status !== 401) {
            showAuthView();
            showError(error);
        }
    }
}

startApplication();
