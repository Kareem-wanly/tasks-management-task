import { useState, type ChangeEvent, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '@/lib/auth/AuthContext';
import { ApiError, errorMessage } from '@/lib/apiClient';
import AuthField from './components/AuthField';
import AuthShell from './components/AuthShell';
import AuthSubmit from './components/AuthSubmit';

type FieldErrors = Record<string, string>;

export default function RegisterPage() {
  const { register } = useAuth();
  const navigate = useNavigate();

  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });

  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [generalError, setGeneralError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

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

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
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

      if (err instanceof ApiError && Object.keys(err.errors).length > 0) {
        const formattedErrors: FieldErrors = {};
        Object.entries(err.errors).forEach(([key, messages]) => {
          formattedErrors[key] = messages[0];
        });
        setFieldErrors(formattedErrors);
      } else {
        setGeneralError(errorMessage(err, 'Registration failed. Please check your data and try again.'));
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AuthShell
      eyebrow="Create account"
      title="Join your workspace"
      description="Sign up to start organising projects and tasks with your team."
      footer={
        <>
          Already have an account?{' '}
          <Link to="/login" className="text-foreground underline decoration-brand/50 underline-offset-4 hover:decoration-brand">
            Sign in
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
          name="name"
          label="Full name"
          value={formData.name}
          onChange={handleChange}
          disabled={isSubmitting}
          placeholder="John Doe"
          autoComplete="name"
          error={fieldErrors.name}
          delay={0.22}
        />
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
          delay={0.26}
        />
        <AuthField
          name="password"
          type="password"
          label="Password"
          value={formData.password}
          onChange={handleChange}
          disabled={isSubmitting}
          placeholder="At least 8 characters"
          autoComplete="new-password"
          error={fieldErrors.password}
          delay={0.3}
        />
        <AuthField
          name="password_confirmation"
          type="password"
          label="Confirm password"
          value={formData.password_confirmation}
          onChange={handleChange}
          disabled={isSubmitting}
          placeholder="Repeat password"
          autoComplete="new-password"
          error={fieldErrors.password_confirmation}
          delay={0.34}
        />
        <AuthSubmit label="Create account" pendingLabel="Creating account..." isSubmitting={isSubmitting} delay={0.38} />
      </form>
    </AuthShell>
  );
}
