import { useState, type ChangeEvent, type FormEvent } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '@/lib/auth/AuthContext';
import { ApiError, errorMessage } from '@/lib/apiClient';
import AuthField from './components/AuthField';
import AuthShell from './components/AuthShell';
import AuthSubmit from './components/AuthSubmit';

type FieldErrors = Record<string, string>;

interface LocationState {
  from?: { pathname: string };
}

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });

  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [generalError, setGeneralError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const from = (location.state as LocationState | null)?.from?.pathname || '/';

  const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
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
    const errors: FieldErrors = {};
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

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
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

      if (err instanceof ApiError && Object.keys(err.errors).length > 0) {
        const formattedErrors: FieldErrors = {};
        Object.entries(err.errors).forEach(([key, messages]) => {
          formattedErrors[key] = messages[0];
        });
        setFieldErrors(formattedErrors);
      } else {
        setGeneralError(errorMessage(err, 'Invalid email or password. Please try again.'));
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AuthShell
      eyebrow="Welcome back"
      title="Sign in to your account"
      description="Enter your details to pick up where you left off."
      footer={
        <>
          Don't have an account?{' '}
          <Link to="/register" className="text-foreground underline decoration-brand/50 underline-offset-4 hover:decoration-brand">
            Sign up
          </Link>
        </>
      }
    >
      {generalError && (
        <p role="alert" className="mb-5 text-sm text-destructive">
          {generalError}
        </p>
      )}

      <form className="space-y-5" onSubmit={handleSubmit} noValidate>
        <AuthField
          name="email"
          type="email"
          label="Email address"
          value={formData.email}
          onChange={handleChange}
          disabled={isSubmitting}
          placeholder="you@company.com"
          autoComplete="email"
          error={fieldErrors.email}
          delay={0.22}
        />
        <AuthField
          name="password"
          type="password"
          label="Password"
          value={formData.password}
          onChange={handleChange}
          disabled={isSubmitting}
          placeholder="••••••••"
          autoComplete="current-password"
          error={fieldErrors.password}
          delay={0.27}
        />
        <AuthSubmit label="Sign in" pendingLabel="Signing in..." isSubmitting={isSubmitting} delay={0.32} />
      </form>
    </AuthShell>
  );
}
