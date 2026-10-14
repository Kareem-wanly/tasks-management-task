import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import './RegisterPage.css';

export default function RegisterPage() {
  const { register } = useAuth();
  const navigate = useNavigate();

  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });

  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});
  const [generalError, setGeneralError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));

    if (fieldErrors[name]) {
      setFieldErrors((prev) => ({ ...prev, [name]: '' }));
    }
    if (generalError) {
      setGeneralError('');
    }
  };

  const validateForm = () => {
    const errors = {};
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!formData.name.trim()) {
      errors.name = 'Full name is required.';
    } else if (formData.name.trim().length < 2) {
      errors.name = 'Name must be at least 2 characters.';
    }

    if (!formData.email.trim()) {
      errors.email = 'Email address is required.';
    } else if (!emailRegex.test(formData.email.trim())) {
      errors.email = 'Please enter a valid email address.';
    }

    if (!formData.password) {
      errors.password = 'Password is required.';
    } else if (formData.password.length < 8) {
      errors.password = 'Password must be at least 8 characters.';
    }

    if (!formData.password_confirmation) {
      errors.password_confirmation = 'Please confirm your password.';
    } else if (formData.password !== formData.password_confirmation) {
      errors.password_confirmation = 'Passwords do not match.';
    }

    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (isSubmitting) return;

    setGeneralError('');
    setFieldErrors({});

    if (!validateForm()) return;

    setIsSubmitting(true);

    const payload = {
      name: formData.name.trim(),
      email: formData.email.trim(),
      password: formData.password,
      password_confirmation: formData.password_confirmation,
    };

    try {
      await register(payload);
      navigate('/', { replace: true });
    } catch (err) {
      console.error('Registration error:', err);

      if (err.errors && Object.keys(err.errors).length > 0) {
        const formattedErrors = {};
        Object.entries(err.errors).forEach(([key, messages]) => {
          formattedErrors[key] = Array.isArray(messages) ? messages[0] : messages;
        });
        setFieldErrors(formattedErrors);
      } else {
        setGeneralError(
          err.message || err.data?.message || 'Registration failed. Please check your data and try again.'
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="register-container">
      <div className="register-card">
        <div className="register-header">
          <div className="register-logo">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
          </div>
          <h1 className="register-title">Create an account</h1>
          <p className="register-subtitle">Sign up to get started with task management</p>
        </div>

        {generalError && <div className="error-alert">{generalError}</div>}

        <form className="register-form" onSubmit={handleSubmit} noValidate>
          <div className="form-group">
            <label className="form-label" htmlFor="name">
              Full Name
            </label>
            <div className="input-wrapper">
              <input
                id="name"
                type="text"
                name="name"
                value={formData.name}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder="John Doe"
                className={`form-input ${fieldErrors.name ? 'input-error' : ''}`}
              />
            </div>
            {fieldErrors.name && <p className="field-error-text">{fieldErrors.name}</p>}
          </div>

          <div className="form-group">
            <label className="form-label" htmlFor="email">
              Email Address
            </label>
            <div className="input-wrapper">
              <input
                id="email"
                type="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder="you@company.com"
                className={`form-input ${fieldErrors.email ? 'input-error' : ''}`}
              />
            </div>
            {fieldErrors.email && <p className="field-error-text">{fieldErrors.email}</p>}
          </div>

          <div className="form-group">
            <label className="form-label" htmlFor="password">
              Password
            </label>
            <div className="input-wrapper">
              <input
                id="password"
                type={showPassword ? 'text' : 'password'}
                name="password"
                value={formData.password}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder="At least 8 characters"
                className={`form-input password-input ${fieldErrors.password ? 'input-error' : ''}`}
              />
              <button
                type="button"
                className="toggle-password-btn"
                onClick={() => setShowPassword(!showPassword)}
                tabIndex="-1"
              >
                {showPassword ? 'Hide' : 'Show'}
              </button>
            </div>
            {fieldErrors.password && <p className="field-error-text">{fieldErrors.password}</p>}
          </div>

          <div className="form-group">
            <label className="form-label" htmlFor="password_confirmation">
              Confirm Password
            </label>
            <div className="input-wrapper">
              <input
                id="password_confirmation"
                type={showConfirmPassword ? 'text' : 'password'}
                name="password_confirmation"
                value={formData.password_confirmation}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder="Repeat password"
                className={`form-input password-input ${fieldErrors.password_confirmation ? 'input-error' : ''}`}
              />
              <button
                type="button"
                className="toggle-password-btn"
                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                tabIndex="-1"
              >
                {showConfirmPassword ? 'Hide' : 'Show'}
              </button>
            </div>
            {fieldErrors.password_confirmation && (
              <p className="field-error-text">{fieldErrors.password_confirmation}</p>
            )}
          </div>

          <button type="submit" className="submit-btn" disabled={isSubmitting}>
            {isSubmitting ? (
              <>
                <span className="spinner" />
                <span>Creating account...</span>
              </>
            ) : (
              'Create account'
            )}
          </button>
        </form>

        <div className="register-footer">
          Already have an account?{' '}
          <Link to="/login" className="signin-link">
            Sign in
          </Link>
        </div>
      </div>
    </div>
  );
}