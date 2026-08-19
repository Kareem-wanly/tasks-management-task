import { Link } from 'react-router-dom';


export default function ForbiddenPage() {
  return (
    <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4 text-center">
      <h1 className="text-6xl font-extrabold text-red-600 mb-2">403</h1>
      <h2 className="text-2xl font-bold text-gray-800 mb-2">So sorry, you are not allowed to access this page.</h2>
      <p className="text-gray-600 mb-6 max-w-md">
        You do not have the necessary permissions to access this page. Please contact your system administrator if you believe this is an error.
      </p>
      <Link
        to="/"
        className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"
      >
        Return to Home
      </Link>
    </div>
  );
}