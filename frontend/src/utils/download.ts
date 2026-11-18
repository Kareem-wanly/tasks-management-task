/** Saves a blob under the file's own name. */
export function saveBlob(blob: Blob, name: string): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  link.click();
  // Give the browser a moment to start the download before freeing the blob.
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}
