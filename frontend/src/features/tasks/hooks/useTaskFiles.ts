import { useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import filesApi from '@/api/filesApi';
import { queryKeys } from '@/api/queryKeys';
import useApiMutation from '@/hooks/useApiMutation';
import { errorMessage } from '@/lib/apiClient';
import { showToast } from '@/lib/toast/ToastContext';
import type { TaskFile, TaskFiles } from '@/types/api';
import { saveBlob } from '@/utils/download';

export interface TaskFilesControl {
  query: UseQueryResult<TaskFiles>;
  upload: (files: File[]) => void;
  remove: (file: TaskFile) => void;
  download: (file: TaskFile) => Promise<void>;
  isPending: boolean;
}

/** A task's files (Bitrix24 step 4.5), read when the task opens. */
export default function useTaskFiles(taskId: number): TaskFilesControl {
  const queryClient = useQueryClient();
  const key = queryKeys.tasks.files(taskId);

  const query = useQuery({
    queryKey: key,
    queryFn: ({ signal }) => filesApi.get(taskId, { signal }),
  });

  const upload = useApiMutation({
    // One at a time: each answer is the whole list, and Bitrix takes them in order.
    mutationFn: async (files: File[]) => {
      let latest: TaskFiles | undefined;
      for (const file of files) latest = await filesApi.upload(taskId, file);
      if (!latest) throw new Error('No file to upload.');
      return latest;
    },
    successMessage: (_: TaskFiles, files: File[]) =>
      files.length === 1 ? `${files[0].name} attached.` : `${files.length} files attached.`,
    errorMessage: 'Failed to attach the file.',
    onSuccess: (files) => queryClient.setQueryData<TaskFiles>(key, files),
    // Earlier files of a batch may already be attached.
    onError: () => void queryClient.invalidateQueries({ queryKey: key }),
  });

  const remove = useApiMutation({
    mutationFn: (file: TaskFile) => filesApi.remove(file.id),
    successMessage: (_: TaskFiles, file: TaskFile) => `${file.name} removed from the task.`,
    errorMessage: 'Failed to remove the file.',
    onSuccess: (files) => queryClient.setQueryData<TaskFiles>(key, files),
  });

  return {
    query,
    upload: (files) => upload.mutate(files),
    remove: (file) => remove.mutate(file),
    download: async (file) => {
      try {
        saveBlob(await filesApi.download(file.id), file.name);
      } catch (err) {
        showToast(errorMessage(err, 'The file could not be downloaded.'), 'error');
      }
    },
    isPending: upload.isPending || remove.isPending,
  };
}
