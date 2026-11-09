import { useState, useEffect, type FormEvent } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { ArrowLeftIcon, Loader2Icon } from 'lucide-react';
import rolesApi, { type RoleInput } from '@/api/rolesApi';
import { ApiError, errorMessage, isAbortError } from '@/lib/apiClient';
import type { Permission, Role } from '@/types/api';
import { useToast } from '@/lib/toast/ToastContext';
import PageHeader from '@/components/layout/PageHeader';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { Button } from '@/components/ui/button';
import RoleDetailsCard, { type FieldErrors, type RoleFormData, type RoleFormField } from './RoleDetailsCard';
import RolePermissionsPanel from './RolePermissionsPanel';

const SYSTEM_ROLES = ['Administrator', 'admin', 'Project Manager', 'Member'];

export default function RoleFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const isEditing = Boolean(id && id !== 'create');
  const { showToast } = useToast();

  const [formData, setFormData] = useState<RoleFormData>({
    name: '',
    display_name: '',
    description: '',
  });

  const [roleDetails, setRoleDetails] = useState<Role | null>(null);
  const [allPermissions, setAllPermissions] = useState<Permission[]>([]);
  const [selectedPermissionIds, setSelectedPermissionIds] = useState<number[]>([]);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<FieldErrors>({});

  useEffect(() => {
    const controller = new AbortController();

    const fetchData = async () => {
      try {
        setLoading(true);
        setError(null);

        setAllPermissions(await rolesApi.getPermissions({ signal: controller.signal }));

        if (isEditing && id) {
          const role = await rolesApi.getById(id, { signal: controller.signal });
          setRoleDetails(role);
          setFormData({
            name: role.name || '',
            display_name: role.display_name || '',
            description: role.description || '',
          });
          setSelectedPermissionIds((role.permissions ?? []).map((p) => p.id));
        }
      } catch (err) {
        if (isAbortError(err)) return;
        console.error('Failed to load role form data:', err);
        setError(errorMessage(err, 'Failed to load role data.'));
      } finally {
        setLoading(false);
      }
    };

    fetchData();
    return () => controller.abort();
  }, [id, isEditing]);

  const isSystemRole = isEditing && SYSTEM_ROLES.includes(roleDetails?.name ?? '');

  const handlePermissionsChange = (ids: number[]) => {
    setSelectedPermissionIds(ids);
    if (validationErrors.permissions) {
      setValidationErrors((prev) => ({ ...prev, permissions: undefined }));
    }
  };

  const handleInputChange = (field: RoleFormField, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    if (validationErrors[field]) {
      setValidationErrors((prev) => ({ ...prev, [field]: undefined }));
    }
  };

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setValidationErrors({});

    const payload: RoleInput = {
      name: formData.name.trim(),
      display_name: formData.display_name.trim() || formData.name.trim(),
      description: formData.description.trim() || null,
      permissions: selectedPermissionIds,
    };

    try {
      if (isEditing && id) {
        await rolesApi.update(id, payload);
        showToast('Role updated successfully.', 'success');
      } else {
        await rolesApi.create(payload);
        showToast('Role created successfully.', 'success');
      }
      navigate('/roles');
    } catch (err) {
      console.error('Save failed:', err);
      if (err instanceof ApiError && Object.keys(err.errors).length > 0) {
        setValidationErrors(err.errors);
      } else {
        setError(errorMessage(err, 'Failed to save role changes.'));
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <LoadingSpinner center size="lg" text="Loading role configuration and permissions..." />;
  }

  return (
    <div className="space-y-8">
      <PageHeader
        eyebrow={
          <Link to="/roles" className="inline-flex items-center gap-1 transition-colors hover:text-brand">
            <ArrowLeftIcon className="size-3 rtl:-scale-x-100" />
            Roles
          </Link>
        }
        title={isEditing ? `Edit ${formData.display_name || formData.name}` : 'New role'}
        description={
          isEditing
            ? 'Configure the permissions and properties of this role.'
            : 'Define a new access role and select granular permissions for assigned users.'
        }
        actions={
          <>
            <Button type="button" variant="outline" onClick={() => navigate('/roles')} disabled={saving}>
              Cancel
            </Button>
            <Button type="submit" form="role-editor-form" variant="brand" disabled={saving}>
              {saving && <Loader2Icon className="animate-spin" />}
              {saving ? 'Saving...' : isEditing ? 'Save changes' : 'Create role'}
            </Button>
          </>
        }
      />

      {error && (
        <p role="alert" className="rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </p>
      )}

      <form id="role-editor-form" onSubmit={handleSubmit} className="grid items-start gap-6 lg:grid-cols-[1fr_1.6fr]">
        <RoleDetailsCard
          formData={formData}
          errors={validationErrors}
          onChange={handleInputChange}
          isEditing={isEditing}
          isSystemRole={isSystemRole}
          usersCount={roleDetails?.users_count ?? 0}
        />
        <RolePermissionsPanel
          allPermissions={allPermissions}
          selectedIds={selectedPermissionIds}
          onChange={handlePermissionsChange}
          error={validationErrors.permissions?.[0]}
        />
      </form>
    </div>
  );
}
