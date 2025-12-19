
import React from 'react';
import { 
  LayoutDashboard, 
  PenTool, 
  BarChart3, 
  Settings, 
  MessageSquare, 
  CalendarDays, 
  Globe2,
  X,
  LogOut,
  Headphones
} from 'lucide-react';
import { User } from '../types';

interface SidebarProps {
  activeTab: string;
  setActiveTab: (tab: string) => void;
  isOpen: boolean;
  onClose: () => void;
  user: User | null;
  onLogout: () => void;
}

const Sidebar: React.FC<SidebarProps> = ({ activeTab, setActiveTab, isOpen, onClose, user, onLogout }) => {
  const menuItems = [
    { id: 'dashboard', label: 'Очередь', icon: LayoutDashboard },
    { id: 'create', label: 'Создать', icon: PenTool },
    { id: 'analytics', label: 'Аналитика', icon: BarChart3 },
    { id: 'calendar', label: 'Календарь', icon: CalendarDays },
    { id: 'podcast', label: 'Аудио / Подкаст', icon: Headphones },
    { id: 'moderation', label: 'Модерация', icon: MessageSquare },
    { id: 'settings', label: 'Настройки', icon: Settings },
  ];

  return (
    <>
      <aside 
        className={`
          fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white shadow-2xl transform transition-transform duration-300 ease-in-out
          md:translate-x-0 md:static md:shadow-none
          ${isOpen ? 'translate-x-0' : '-translate-x-full'}
        `}
      >
        <div className="flex flex-col h-full">
          <div className="p-6 flex items-center justify-between border-b border-slate-800">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                <Globe2 size={20} className="text-white" />
              </div>
              <span className="font-bold text-lg tracking-tight">AI News</span>
            </div>
            <button onClick={onClose} className="md:hidden text-slate-400 hover:text-white">
              <X size={24} />
            </button>
          </div>

          <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
            {menuItems.map((item) => (
              <button
                key={item.id}
                onClick={() => {
                  setActiveTab(item.id);
                  onClose();
                }}
                className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 ${
                  activeTab === item.id 
                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/50' 
                    : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                }`}
              >
                <item.icon size={20} />
                <span className="font-medium">{item.label}</span>
              </button>
            ))}
          </nav>

          <div className="p-4 border-t border-slate-800 bg-slate-900">
            <div className="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-800/50">
              <div className="w-9 h-9 rounded-full bg-gradient-to-tr from-purple-500 to-pink-500 flex items-center justify-center text-sm font-bold">
                {user?.name?.substring(0,2).toUpperCase() || 'AD'}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-white truncate">{user?.name || 'Admin'}</p>
                <p className="text-xs text-slate-500 truncate capitalize">{user?.role || 'Editor'}</p>
              </div>
              <button 
                onClick={onLogout}
                className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-lg transition-colors" 
                title="Выйти"
              >
                <LogOut size={18} />
              </button>
            </div>
          </div>
        </div>
      </aside>
    </>
  );
};

export default Sidebar;
