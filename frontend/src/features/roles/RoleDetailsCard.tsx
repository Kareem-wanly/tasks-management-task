import Panel from '@/components/layout/Panel';
import StatusBadge from '@/components/data/StatusBadge';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export type RoleFormField = 'name' | 'display_name' | 'description';
export type RoleFormData = Record<RoleFormField, string>;
export type FieldErrors = Partial<Record<string, string[]>>;

interface RoleDetailsCardProps {
  formData: RoleFormData;
  errors: FieldErrors;
  onChange: (field: RoleFormField, value: string) => void;
  isEditing: boolean;
  isSystemRole: boolean;
  usersCount: number;
}

export default function RoleDetailsCard({
  formData,
  errors,
  onChange,
  isEditing,
  isSystemRole,
  usersCount,
}: RoleDetailsCardProps) {
  return (
    <Panel title="Role information" description="Identity and metadata for role classification.">
      <FieldGroup>
        <Field data-invalid={Boolean(errors.display_name)}>
          <FieldLabel htmlFor="role-display-name">
            Display name <span className="text-destructive">*</span>
          </FieldLabel>
          <Input
            id="role-display-name"
            placeholder="e.g. Lead Developer"
            value={formData.display_name}
            onChange={(e) => onChange('display_name', e.target.value)}
            aria-invalid={Boolean(errors.display_name)}
            required
          />
          {errors.display_name && <FieldError>{errors.display_name[0]}</FieldError>}
        </Field>

        <Field data-invalid={Boolean(errors.name)}>
          <FieldLabel htmlFor="role-name">
            System key <span className="text-destructive">*</span>
          </FieldLabel>
          <Input
            id="role-name"
            dir="ltr"
            className="font-mono"
            disabled={isSystemRole}
            placeholder="e.g. lead_developer"
            value={formData.name}
            onChange={(e) => onChange('name', e.target.value)}
            aria-invalid={Boolean(errors.name)}
            required
          />
          {errors.name && <FieldError>{errors.name[0]}</FieldError>}
          {isSystemRole && <FieldDescription>Protected system slug cannot be renamed.</FieldDescription>}
        </Field>

        <Field data-invalid={Boolean(errors.description)}>
          <FieldLabel htmlFor="role-description">Description</FieldLabel>
          <Textarea
            id="role-description"
            rows={3}
            placeholder="Briefly describe what this role allows..."
            value={formData.description}
            onChange={(e) => onChange('description', e.target.value)}
            aria-invalid={Boolean(errors.description)}
          />
          {errors.description && <FieldError>{errors.description[0]}</FieldError>}
        </Field>
      </FieldGroup>

      {isEditing && (
        <dl className="mt-6 grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 border-t border-border/60 pt-5 text-sm">
          <dt className="text-muted-foreground">Assigned users</dt>
          <dd className="text-end tabular-nums">{usersCount}</dd>
          <dt className="text-muted-foreground">Role type</dt>
          <dd className="text-end">
            <StatusBadge tone={isSystemRole ? 'brand' : 'neutral'} plain>
              {isSystemRole ? 'System protected' : 'Custom'}
            </StatusBadge>
          </dd>
        </dl>
      )}
    </Panel>
  );
}
