import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import './LoginPage.css';

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });

  const [showPassword, setShowPassword] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});
  const [generalError, setGeneralError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const from = location.state?.from?.pathname || '/';

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

    if (!formData.email.trim()) {
      errors.email = 'Email address is required.';
    } else if (!emailRegex.test(formData.email.trim())) {
      errors.email = 'Please enter a valid email address.';
    }

    if (!formData.password) {
      errors.password = 'Password is required.';
    } else if (formData.password.length < 6) {
      errors.password = 'Password must be at least 6 characters.';
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

    try {
      await login(formData);
      navigate(from, { replace: true });
    } catch (err) {
      console.error('Login error:', err);

      if (err.errors && Object.keys(err.errors).length > 0) {
        const formattedErrors = {};
        Object.entries(err.errors).forEach(([key, messages]) => {
          formattedErrors[key] = Array.isArray(messages) ? messages[0] : messages;
        });
        setFieldErrors(formattedErrors);
      } else {
        setGeneralError(
          err.message || err.data?.message || 'Invalid email or password. Please try again.'
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="login-container">
      <div className="login-card">
        {/* Header */}
        <div className="login-header">
          <div className="login-logo">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
          </div>
          <h1 className="login-title">Sign in to your account</h1>
          <p className="login-subtitle">Welcome back! Please enter your details.</p>
        </div>

        {/* General Error Alert */}
        {generalError && (
          <div className="error-alert">
            {generalError}
          </div>
        )}

        <form className="login-form" onSubmit={handleSubmit} noValidate>
          {/* Email Field */}
          <div className="form-group">
            <label className="form-label" htmlFor="email">
              Email address
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
            {fieldErrors.email && (
              <p className="field-error-text">{fieldErrors.email}</p>
            )}
          </div>

          {/* Password Field */}
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
                placeholder="••••••••"
                className={`form-input password-input ${fieldErrors.password ? 'input-error' : ''}`}
              />
              <button
                type="button"
                className="toggle-password-btn"
                onClick={() => setShowPassword(!showPassword)}
                tabIndex="-1"
                aria-label="Toggle password visibility"
              >
                {showPassword ? 'Hide' : 'Show'}
              </button>
            </div>
            {fieldErrors.password && (
              <p className="field-error-text">{fieldErrors.password}</p>
            )}
          </div>

          {/* Submit Button */}
          <button type="submit" className="submit-btn" disabled={isSubmitting}>
            {isSubmitting ? (
              <>
                <span className="spinner" />
                <span>Signing in...</span>
              </>
            ) : (
              'Sign in'
            )}
          </button>
        </form>

        {/* Footer */}
        <div className="login-footer">
          Don't have an account?{' '}
          <Link to="/register" className="signup-link">
            Sign up
          </Link>
        </div>
      </div>
    </div>
  );
}