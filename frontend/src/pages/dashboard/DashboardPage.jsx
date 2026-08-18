import { useAuth } from '../../context/AuthContext';
import Can from '../../components/common/Can';

export default function DashboardPage() {
  const { user, roles, permissions, logout } = useAuth();

  return (
    <div className="min-h-screen bg-gray-100 p-8">
      <div className="max-w-4xl mx-auto bg-white rounded-xl shadow-md p-6">
        <div className="flex justify-between items-center pb-4 border-b">
          <h1 className="text-2xl font-bold text-gray-800">Dashboard</h1>
          <button
            onClick={logout}
            className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm"
          >
            Logout
          </button>
        </div>

        <div className="mt-6 space-y-4">
          <p className="text-gray-700">
            Welcome, <span className="font-semibold text-indigo-600">{user?.name}</span> ({user?.email})
          </p>

          <div className="bg-gray-50 p-4 rounded-lg">
            <h3 className="font-semibold text-gray-700 mb-2">Roles:</h3>
            <div className="flex gap-2">
              {roles?.length ? (
                roles.map((r, i) => (
                  <span key={i} className="px-2.5 py-1 bg-indigo-100 text-indigo-800 text-xs font-semibold rounded-full">
                    {r}
                  </span>
                ))
              ) : (
                <span className="text-sm text-gray-500">No specific roles assigned</span>
              )}
            </div>
          </div>

          <div className="bg-gray-50 p-4 rounded-lg">
            <h3 className="font-semibold text-gray-700 mb-2">Permissions:</h3>
            <div className="flex flex-wrap gap-2">
              {permissions?.length ? (
                permissions.map((p, i) => (
                  <span key={i} className="px-2.5 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                    {p}
                  </span>
                ))
              ) : (
                <span className="text-sm text-gray-500">No specific permissions assigned</span>
              )}
            </div>
          </div>

          
          <Can role="admin">
            <div className="p-3 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg text-sm">
              This element is only visible to administrators (Admin).
            </div>
          </Can>
        </div>
      </div>
    </div>
  );
}