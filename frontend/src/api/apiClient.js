const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';


async function request(endpoint, options = {}) {
  const url = `${BASE_URL}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;

  const token = localStorage.getItem('auth_token') || localStorage.getItem('token');

  const headers = {
    'Accept': 'application/json',
    ...options.headers,
  };

  if (!(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const config = {
    method: options.method || 'GET',
    headers,
    ...options,
  };

  if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
    config.body = JSON.stringify(config.body);
  }

  let response;
  try {
    response = await fetch(url, config);
  } catch (networkError) {
    console.error('Fetch error:', networkError);
    const error = new Error('Could not connect to the server. Please check your internet connection.');
    error.status = 0;
    error.isNetworkError = true;
    throw error;
  }

  if (response.status === 204) {
    return { success: true, data: null };
  }

  let data = null;
  const contentType = response.headers.get('content-type');
  if (contentType && contentType.includes('application/json')) {
    try {
      data = await response.json();
    } catch {
      data = null;
    }
  }

  if (!response.ok) {
    const error = new Error(data?.message || 'An error occurred while processing the request.');
    error.status = response.status;
    error.data = data;
    error.errors = data?.errors || {};

    switch (response.status) {
      case 401: 
        localStorage.removeItem('auth_token');
        localStorage.removeItem('token');
        localStorage.removeItem('auth_user');
        localStorage.removeItem('user');
        window.dispatchEvent(new Event('auth:unauthorized'));
        break;

      case 403: 
        error.message = data?.message || 'You are not authorized to perform this action.';
        break;

      case 404: 
        error.message = data?.message || 'The requested resource was not found.';
        break;

      case 422: 
        error.message = data?.message || 'Please ensure that the data entered is correct.';
        break;

      case 500:
        error.message = 'An internal server error occurred. Please try again later.';
        break;

      default:
        break;
    }

    throw error;
  }

  return data;
}

const apiClient = {
  get: (endpoint, options = {}) => request(endpoint, { ...options, method: 'GET' }),
  post: (endpoint, body, options = {}) => request(endpoint, { ...options, method: 'POST', body }),
  put: (endpoint, body, options = {}) => request(endpoint, { ...options, method: 'PUT', body }),
  patch: (endpoint, body, options = {}) => request(endpoint, { ...options, method: 'PATCH', body }),
  delete: (endpoint, options = {}) => request(endpoint, { ...options, method: 'DELETE' }),
};

export default apiClient;
export { BASE_URL, request };