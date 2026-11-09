import { useState, useEffect, useCallback, type FormEvent } from 'react';
import usersApi from '@/api/usersApi';
import projectsApi from '@/api/projectsApi';
import { errorMessage } from '@/lib/apiClient';
import type { User, UserOption } from '@/types/api';
import FormDialog from '@/components/ui/FormDialog';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { Field, FieldLabel } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface AddMemberModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  projectId: number;
  existingMembers?: User[];
  ownerId?: number;
}

export default function AddMemberModal({
  isOpen,
  onClose,
  onSuccess,
  projectId,
  existingMembers = [],
  ownerId,
}: AddMemberModalProps) {
  const [users, setUsers] = useState<UserOption[]>([]);
  const [selectedUserId, setSelectedUserId] = useState('');
  const [loadingUsers, setLoadingUsers] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchAvailableUsers = useCallback(async () => {
    try {
      setLoadingUsers(true);
      const allUsers = await usersApi.options();

      const currentMemberIds = new Set(existingMembers.map((m) => m.id));
      if (ownerId) currentMemberIds.add(ownerId);

      const available = allUsers.filter((u) => !currentMemberIds.has(u.id));
      setUsers(available);

      if (available.length > 0) {
        setSelectedUserId(String(available[0].id));
      }
    } catch (err) {
      console.error('Failed to load users:', err);
      setError('Failed to fetch available users list.');
    } finally {
      setLoadingUsers(false);
    }
  }, [existingMembers, ownerId]);

  useEffect(() => {
    if (isOpen) {
      setError(null);
      setSelectedUserId('');
      fetchAvailableUsers();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- load once per open; existingMembers is a new array on every parent render
  }, [isOpen]);

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!selectedUserId) {
      setError('Please select a user to add.');
      return;
    }

    try {
      setSubmitting(true);
      setError(null);
      await projectsApi.addMember(projectId, { user_id: Number(selectedUserId) });
      onSuccess();
      onClose();
    } catch (err) {
      console.error('Failed to add member:', err);
      setError(errorMessage(err, 'Failed to add member to the project.'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <FormDialog
      open={isOpen}
      onOpenChange={(open) => !open && onClose()}
      title="Add team member"
      description="Give another user access to this project."
      onSubmit={handleSubmit}
      submitLabel={submitting ? 'Adding...' : 'Add to project'}
      submitting={submitting}
    >
      {error && (
        <p role="alert" className="rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </p>
      )}

      {loadingUsers ? (
        <LoadingSpinner text="Loading available users..." className="py-6" />
      ) : users.length === 0 ? (
        <p className="py-4 text-sm leading-6 text-muted-foreground">
          All system users are already members of this project.
        </p>
      ) : (
        <Field>
          <FieldLabel htmlFor="select-user">
            Select user <span className="text-destructive">*</span>
          </FieldLabel>
          <Select value={selectedUserId} onValueChange={setSelectedUserId} disabled={submitting}>
            <SelectTrigger id="select-user" className="w-full">
              <SelectValue placeholder="Choose a user" />
            </SelectTrigger>
            <SelectContent>
              {users.map((user) => (
                <SelectItem key={user.id} value={String(user.id)}>
                  {user.name} <span className="text-muted-foreground">({user.email})</span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Field>
      )}
    </FormDialog>
  );
}
