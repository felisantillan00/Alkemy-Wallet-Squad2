const configuredBaseUrl = document.querySelector('meta[name="api-base-url"]')?.content;
export const API_BASE_URL = (configuredBaseUrl || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');
const TOKEN_KEY = 'wallet_token';

export class ApiError extends Error {
    constructor(message, status, details = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.details = details;
    }
}

export function getToken() {
    return sessionStorage.getItem(TOKEN_KEY);
}

export function saveToken(token) {
    sessionStorage.setItem(TOKEN_KEY, token);
}

export function removeToken() {
    sessionStorage.removeItem(TOKEN_KEY);
}

function errorMessage(responseData, status) {
    if (responseData?.errors) {
        const validationMessages = Object.values(responseData.errors).flat();
        if (validationMessages.length > 0) {
            return validationMessages.join(' ');
        }
    }
    return responseData?.message || `La API respondió con el estado ${status}.`;
}

async function parseResponse(response) {
    if (response.status === 204) {
        return null;
    }
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
        return null;
    }
    return response.json();
}

export async function apiRequest(path, options = {}) {
    const {
        method = 'GET',
        body,
        requiresAuth = true,
    } = options;
    const headers = new Headers({ Accept: 'application/json' });
    const token = getToken();

    if (requiresAuth && token) {
        headers.set('Authorization', `Bearer ${token}`);
    }

    let requestBody = body;

    if (body !== undefined && !(body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
        requestBody = JSON.stringify(body);
    }

    let response;

    try {
        response = await fetch(`${API_BASE_URL}${path}`, {
            method,
            headers,
            body: requestBody,
        });
    } catch (error) {
        throw new ApiError(
            `No se pudo conectar con la API en ${API_BASE_URL}. Verificá que Laravel esté iniciado y que la URL sea correcta.`,
            0,
            { cause: error },
        );
    }

    const responseData = await parseResponse(response);

    if (!response.ok) {
        if (requiresAuth && response.status === 401) {
            removeToken();
            window.dispatchEvent(new CustomEvent('wallet:unauthorized'));
        }
        throw new ApiError(errorMessage(responseData, response.status), response.status, responseData);
    }

    return responseData;
}

export function registerUser(fields) {
    return apiRequest('/auth/register', {
        method: 'POST',
        body: fields,
        requiresAuth: false,
    });
}

export function loginUser(credentials) {
    return apiRequest('/auth/login', {
        method: 'POST',
        body: credentials,
        requiresAuth: false,
    });
}

export function checkSession() {
    return apiRequest('/auth/check');
}

export function getProfile() {
    return apiRequest('/profile');
}

export function updateProfile(profileData) {
    profileData.set('_method', 'PATCH');
    return apiRequest('/profile', {
        method: 'POST',
        body: profileData,
    });
}

export function getAccount() {
    return apiRequest('/account');
}

export function createDeposit(amount) {
    return apiRequest('/deposits', {
        method: 'POST',
        body: { amount },
    });
}

export function createTransfer(destinationCbu, amount) {
    return apiRequest('/transfers', {
        method: 'POST',
        body: {
            destination_cbu: destinationCbu,
            amount,
        },
    });
}

export function getMovements(page = 1, perPage = 10) {
    const query = new URLSearchParams({
        page: String(page),
        per_page: String(perPage),
        sort: 'created_at',
        order: 'desc',
    });
    return apiRequest(`/movements?${query.toString()}`);
}

export function getRecipients(userId) {
    return apiRequest(`/cbu/users/${encodeURIComponent(userId)}`);
}

export function addRecipient(cbu, userId) {
    return apiRequest(`/cbu/${encodeURIComponent(cbu)}/users/${encodeURIComponent(userId)}`, {
        method: 'POST',
    });
}

export function deleteRecipient(cbu, userId) {
    return apiRequest(`/cbu/${encodeURIComponent(cbu)}/users/${encodeURIComponent(userId)}`, {
        method: 'DELETE',
    });
}

export function simulateFixedTerm(amount, termDays) {
    return apiRequest('/investments/fixed-term/simulate', {
        method: 'POST',
        body: {
            amount,
            term_days: termDays,
        },
    });
}

export function profileImageUrl(imagePath) {
    if (!imagePath) {
        return null;
    }
    const apiUrl = new URL(API_BASE_URL);
    return `${apiUrl.origin}/storage/${String(imagePath).replace(/^\//, '')}`;
}

function adminListPath(resource, options) {
    const query = new URLSearchParams();
    Object.entries(options).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            query.set(key, String(value));
        }
    });
    return `/admin/${resource}?${query.toString()}`;
}

export function getAdminUsers(options = {}) {
    return apiRequest(adminListPath('users', {
        page: options.page || 1,
        per_page: options.perPage || 10,
        sort: options.sort || 'created_at',
        order: options.order || 'desc',
    }));
}

export function getAdminUser(userId) {
    return apiRequest(`/admin/users/${encodeURIComponent(userId)}`);
}

export function createAdminUser(fields) {
    return apiRequest('/admin/users', { method: 'POST', body: fields });
}

export function updateAdminUser(userId, fields) {
    return apiRequest(`/admin/users/${encodeURIComponent(userId)}`, { method: 'PATCH', body: fields });
}

export function deleteAdminUser(userId) {
    return apiRequest(`/admin/users/${encodeURIComponent(userId)}`, { method: 'DELETE' });
}

export function getAdminAccounts(options = {}) {
    return apiRequest(adminListPath('accounts', {
        page: options.page || 1,
        per_page: options.perPage || 10,
        sort: options.sort || 'created_at',
        order: options.order || 'desc',
    }));
}

export function getAdminAccount(accountId) {
    return apiRequest(`/admin/accounts/${encodeURIComponent(accountId)}`);
}

export function createAdminAccount(fields) {
    return apiRequest('/admin/accounts', { method: 'POST', body: fields });
}

export function updateAdminAccount(accountId, fields) {
    return apiRequest(`/admin/accounts/${encodeURIComponent(accountId)}`, { method: 'PATCH', body: fields });
}

export function deleteAdminAccount(accountId) {
    return apiRequest(`/admin/accounts/${encodeURIComponent(accountId)}`, { method: 'DELETE' });
}

export function getAdminMovements(options = {}) {
    return apiRequest(adminListPath('movements', {
        page: options.page || 1,
        per_page: options.perPage || 10,
        sort: options.sort || 'created_at',
        order: options.order || 'desc',
        account_id: options.accountId,
        user_id: options.userId,
    }));
}

export function getAdminMovement(movementId) {
    return apiRequest(`/admin/movements/${encodeURIComponent(movementId)}`);
}

export function createAdminMovement(fields) {
    return apiRequest('/admin/movements', { method: 'POST', body: fields });
}

export function updateAdminMovement(movementId, fields) {
    return apiRequest(`/admin/movements/${encodeURIComponent(movementId)}`, { method: 'PATCH', body: fields });
}

export function deleteAdminMovement(movementId) {
    return apiRequest(`/admin/movements/${encodeURIComponent(movementId)}`, { method: 'DELETE' });
}
export function checkAdmin() {
    return apiRequest('/admin/check');
}
