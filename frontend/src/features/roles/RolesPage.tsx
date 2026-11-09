import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { PlusIcon, ShieldIcon } from 'lucide-react';
import rolesApi from '@/api/rolesApi';
import { errorMessage, isAbortError } from '@/lib/apiClient';
import type { Role } from '@/types/api';
import { useAuth } from '@/lib/auth/AuthContext';
import { useToast } from '@/lib/toast/ToastContext';
import EmptyState from '@/components/data/EmptyState';
import Stagger from '@/components/motion/Stagger';
import StaggerItem from '@/components/motion/StaggerItem';
import PageHeader from '@/components/layout/PageHeader';
import ConfirmModal from '@/components/ui/ConfirmModal';
import ErrorState from '@/components/ui/ErrorState';
import { Button } from '@/components/ui/button';
import RoleCard from './RoleCard';

const PROTECTED_ROLE_NAMES = ['administrator', 'admin', 'super-admin', 'project manager', 'member'];
const SKELETON_CARDS = [1, 2, 3];

const isRoleProtected = (name: string) => PROTECTED_ROLE_NAMES.includes(name.trim().toLowerCase());

export default function RolesPage() {
  const { can } = useAuth();
  const { showToast } = useToast();

  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [roleToDelete, setRoleToDelete] = useState<Role | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const fetchRoles = useCallback(async (signal?: AbortSignal) => {
    try {
      setLoading(true);
      setError(null);
      setRoles(await rolesApi.getAll({ signal }));
    } catch (err) {
      if (isAbortError(err)) return;
      console.error('Failed to load roles:', err);
      setError(errorMessage(err, 'Failed to fetch roles list.'));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    fetchRoles(controller.signal);

    return () => {
      controller.abort();
    };
  }, [fetchRoles]);

  const handleDeleteRole = async () => {
    if (!roleToDelete) return;
    try {
      setDeleteLoading(true);
      await rolesApi.delete(roleToDelete.id);
      showToast(`Role "${roleToDelete.display_name || roleToDelete.name}" was deleted successfully.`, 'success');
      setRoleToDelete(null);
      fetchRoles();
    } catch (err) {
      showToast(errorMessage(err, 'Failed to delete role.'), 'error');
    } finally {
      setDeleteLoading(false);
    }
  };

  const canCreateRole = can('roles.create') || can('roles.manage');
  const canEditRole = can('roles.update') || can('roles.manage');
  const canDeleteRole = can('roles.delete') || can('roles.manage');

  const renderBody = () => {
    if (loading) {
      return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {SKELETON_CARDS.map((key) => (
            <div key={key} className="surface h-52 shimmer" />
          ))}
        </div>
      );
    }
    if (error) {
      return <ErrorState message={error} onRetry={() => fetchRoles()} />;
    }
    if (roles.length === 0) {
      return (
        <div className="surface">
          <EmptyState
            icon={ShieldIcon}
            title="No roles defined yet"
            description="Create roles to configure granular access control for your team."
          />
        </div>
      );
    }
    return (
      <Stagger className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {roles.map((role) => (
          <StaggerItem key={role.id} className="h-full">
            <RoleCard
              role={role}
              isProtected={isRoleProtected(role.name)}
              canEdit={canEditRole}
              canDelete={canDeleteRole}
              onDelete={setRoleToDelete}
            />
          </StaggerItem>
        ))}
      </Stagger>
    );
  };

  return (
    <div className="space-y-8">
      <PageHeader
        eyebrow="Management"
        title="Roles"
        description="Define system roles, access policies, and permission boundaries."
        actions={
          canCreateRole && (
            <Button asChild variant="brand">
              <Link to="/roles/create">
                <PlusIcon />
                New role
              </Link>
            </Button>
          )
        }
      />

      {renderBody()}

      {roleToDelete && (
        <ConfirmModal
          isOpen={Boolean(roleToDelete)}
          title="Delete Role"
          message={`Are you sure you want to delete the role "${roleToDelete.display_name || roleToDelete.name}"? Users with this role will lose these permissions.`}
          confirmText="Delete Role"
          danger={true}
          loading={deleteLoading}
          onConfirm={handleDeleteRole}
          onClose={() => setRoleToDelete(null)}
        />
      )}
    </div>
  );
}
