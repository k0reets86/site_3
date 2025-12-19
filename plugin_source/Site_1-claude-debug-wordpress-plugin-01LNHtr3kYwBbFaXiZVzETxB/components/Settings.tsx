
import React, { useState } from 'react';
import { Save, Server, Key, Database, Plus, Trash2, Search, X, UserPlus, Shield, Puzzle, Check } from 'lucide-react';
import { Source, SourceCategory, User, CustomApiKey } from '../types';
import { mockUsers } from '../mockData';

// --- FULL SOURCES LIST ---
const INITIAL_SOURCES: Source[] = [
    // EMERGENCY / HOT
    { id: 'g1', name: 'Google News: "München Alarm"', url: 'news.google.com/rss/search?q=München+Feuer+OR+Unfall', trustScore: 0.8, category: 'emergency', active: true },
    { id: 'g2', name: 'Warnung.bund.de (Bayern)', url: 'warnung.bund.de/api/rss/bayern', trustScore: 1.0, category: 'emergency', active: true },
    
    // OFFICIAL MUNICH/BAVARIA
    { id: 'o1', name: 'Stadt München (Ukraine Infos)', url: 'stadt.muenchen.de/infos/ukraine.html', trustScore: 1.0, category: 'official', active: true },
    { id: 'o2', name: 'Landeshauptstadt München (Presse)', url: 'ru.muenchen.de', trustScore: 1.0, category: 'official', active: true },
    { id: 'o3', name: 'Bayerische Staatsregierung', url: 'bayern.de', trustScore: 1.0, category: 'official', active: true },
    { id: 'o4', name: 'Bundesagentur für Arbeit', url: 'arbeitsagentur.de', trustScore: 1.0, category: 'official', active: true },
    { id: 'o5', name: 'MVG Ticker (Transport)', url: 'mvg.de/ticker', trustScore: 0.95, category: 'official', active: true },
    { id: 'o7', name: 'BAMF Presse', url: 'bamf.de', trustScore: 1.0, category: 'official', active: true },
    
    // MEDIA MUNICH
    { id: 'm1', name: 'Süddeutsche Zeitung (München)', url: 'sueddeutsche.de/muenchen/rss', trustScore: 0.85, category: 'media', active: true },
    { id: 'm2', name: 'Münchner Merkur', url: 'merkur.de/lokales/muenchen', trustScore: 0.80, category: 'media', active: true },
    { id: 'm4', name: 'BR24 Bayern', url: 'br.de/nachrichten/bayern', trustScore: 0.95, category: 'media', active: true },
    { id: 'm5', name: 'Radio Gong 96.3', url: 'radiogong.de', trustScore: 0.75, category: 'media', active: true },
    
    // INTERNATIONAL
    { id: 'f1', name: 'Tagesschau', url: 'tagesschau.de', trustScore: 0.95, category: 'international', active: true },
    { id: 'f2', name: 'Deutsche Welle (Learn German)', url: 'dw.com', trustScore: 0.95, category: 'international', active: true },
    { id: 'f4', name: 'BBC News Europe', url: 'bbc.com/news/world/europe', trustScore: 0.9, category: 'international', active: true },
    
    // UKRAINE
    { id: 'u1', name: 'МЗС України (Консульство)', url: 'munich.mfa.gov.ua/news', trustScore: 1.0, category: 'ukraine', active: true },
    { id: 'u2', name: 'Visit Ukraine', url: 'visitukraine.today', trustScore: 0.85, category: 'ukraine', active: true },
    { id: 'u3', name: 'Suspilne', url: 'suspilne.media', trustScore: 0.9, category: 'ukraine', active: true },
    { id: 'u4', name: 'Ukrinform', url: 'ukrinform.ua', trustScore: 0.9, category: 'ukraine', active: true },
    
    // SOCIAL / TELEGRAM
    { id: 's1', name: 'TG: Українці в Мюнхені', url: 't.me/ukrainians_in_munich', trustScore: 0.4, category: 'social', active: true },
    { id: 's2', name: 'TG: Помощь украинцам', url: 't.me/help_ukraine_de', trustScore: 0.4, category: 'social', active: true },
];

const Settings: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'sources' | 'api' | 'team'>('sources');
  const [isAddSourceOpen, setIsAddSourceOpen] = useState(false);
  const [isAddUserOpen, setIsAddUserOpen] = useState(false);
  const [sources, setSources] = useState<Source[]>(INITIAL_SOURCES);
  const [isSaving, setIsSaving] = useState(false);
  
  // --- SOURCES LOGIC ---
  const [newSource, setNewSource] = useState({ name: '', url: '', category: 'official' });
  const [searchQuery, setSearchQuery] = useState('');

  const handleAddSource = () => {
    if (!newSource.name || !newSource.url) return;
    const source: Source = {
      id: Date.now().toString(),
      name: newSource.name,
      url: newSource.url,
      category: newSource.category as SourceCategory,
      trustScore: 0.5,
      active: true
    };
    setSources([source, ...sources]); // Add to top
    setNewSource({ name: '', url: '', category: 'official' });
    setIsAddSourceOpen(false);
  };

  const toggleSource = (id: string) => {
    setSources(sources.map(s => s.id === id ? { ...s, active: !s.active } : s));
  };

  const deleteSource = (id: string) => {
    if (window.confirm('Удалить источник из списка отслеживания?')) {
        setSources(sources.filter(s => s.id !== id));
    }
  };

  const filteredSources = sources.filter(s => 
    s.name.toLowerCase().includes(searchQuery.toLowerCase()) || 
    s.category.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const handleSave = () => {
      setIsSaving(true);
      setTimeout(() => {
          setIsSaving(false);
          alert('Настройки успешно сохранены!');
      }, 1000);
  }

  // --- API LOGIC ---
  const [customKeys, setCustomKeys] = useState<CustomApiKey[]>([]);
  const [newKey, setNewKey] = useState({ serviceName: '', key: '' });
  const addCustomKey = () => {
    if(!newKey.serviceName || !newKey.key) return;
    setCustomKeys([...customKeys, { id: Date.now().toString(), serviceName: newKey.serviceName, key: newKey.key, addedAt: new Date().toISOString() }]);
    setNewKey({ serviceName: '', key: '' });
  };
  const deleteKey = (id: string) => setCustomKeys(customKeys.filter(k => k.id !== id));

  // --- TEAM LOGIC ---
  const [users, setUsers] = useState<User[]>(mockUsers);
  const [newUser, setNewUser] = useState({ name: '', email: '', role: 'editor' });
  
  const handleAddUser = () => {
    if(!newUser.name || !newUser.email) return;
    const user: User = {
        id: Date.now().toString(),
        name: newUser.name,
        email: newUser.email,
        role: newUser.role as 'admin'|'editor',
        status: 'invited'
    };
    setUsers([...users, user]);
    setNewUser({ name: '', email: '', role: 'editor' });
    setIsAddUserOpen(false);
  };

  const deleteUser = (id: string) => {
      if(confirm('Удалить пользователя?')) setUsers(users.filter(u => u.id !== id));
  };

  const categoryColors: Record<SourceCategory, string> = {
    official: 'bg-blue-50 text-blue-700 border-blue-100',
    media: 'bg-purple-50 text-purple-700 border-purple-100',
    social: 'bg-orange-50 text-orange-700 border-orange-100',
    emergency: 'bg-red-50 text-red-700 border-red-100',
    ukraine: 'bg-yellow-50 text-yellow-700 border-yellow-100',
    international: 'bg-slate-100 text-slate-700 border-slate-200'
  };

  return (
    <div className="space-y-6 pb-20">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <h1 className="text-2xl font-bold text-slate-900">Настройки системы</h1>
        <button 
            onClick={handleSave}
            disabled={isSaving}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 shadow-lg shadow-blue-900/10 disabled:opacity-70"
        >
          {isSaving ? <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : <Save size={18} />} 
          {isSaving ? 'Сохранение...' : 'Сохранить изменения'}
        </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 p-1.5 bg-slate-100 rounded-xl w-full md:w-fit overflow-x-auto">
        {[
            { id: 'sources', label: 'Источники' },
            { id: 'api', label: 'Интеграции API' },
            { id: 'team', label: 'Команда' }
        ].map(tab => (
          <button 
            key={tab.id}
            onClick={() => setActiveTab(tab.id as any)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap ${activeTab === tab.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* === TAB: SOURCES === */}
      {activeTab === 'sources' && (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
              <input 
                type="text" 
                placeholder="Поиск источника (Мюнхен, BAMF)..." 
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" 
              />
            </div>
            <button 
              onClick={() => setIsAddSourceOpen(true)}
              className="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center gap-2"
            >
              <Plus size={16} /> Добавить источник
            </button>
          </div>
          
          <div className="overflow-x-auto max-h-[600px] scroll-smooth">
            <table className="w-full text-left">
              <thead className="bg-slate-50 text-xs uppercase text-slate-500 font-semibold sticky top-0 z-10 shadow-sm">
                <tr>
                  <th className="px-6 py-3 bg-slate-50">Название / URL</th>
                  <th className="px-6 py-3 bg-slate-50">Категория</th>
                  <th className="px-6 py-3 bg-slate-50">Доверие</th>
                  <th className="px-6 py-3 text-right bg-slate-50">Действия</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {filteredSources.map(source => (
                  <tr key={source.id} className={`hover:bg-slate-50 transition-colors ${!source.active ? 'opacity-60 bg-slate-50/50' : ''}`}>
                    <td className="px-6 py-4">
                      <div className="font-medium text-slate-900">{source.name}</div>
                      <div className="text-xs text-slate-400 font-mono truncate max-w-[300px]">{source.url}</div>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`px-2.5 py-1 rounded-full text-xs font-medium border ${categoryColors[source.category]}`}>
                        {source.category}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-2">
                        <div className="w-24 h-2 bg-slate-100 rounded-full overflow-hidden">
                          <div className={`h-full rounded-full ${source.trustScore > 0.8 ? 'bg-green-500' : 'bg-amber-500'}`} style={{width: `${source.trustScore * 100}%`}}></div>
                        </div>
                        <span className="text-xs text-slate-500">{source.trustScore}</span>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-3">
                        <button 
                          onClick={() => toggleSource(source.id)}
                          className={`w-10 h-6 rounded-full transition-colors relative ${source.active ? 'bg-blue-600' : 'bg-slate-200'}`}
                          title={source.active ? 'Отключить' : 'Включить'}
                        >
                          <span className={`absolute top-1 left-1 w-4 h-4 bg-white rounded-full transition-transform ${source.active ? 'translate-x-4' : ''}`} />
                        </button>
                        <button onClick={() => deleteSource(source.id)} className="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                          <Trash2 size={18} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* === TAB: API INTEGRATIONS === */}
      {activeTab === 'api' && (
        <div className="space-y-8">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                <h3 className="font-bold text-lg text-slate-900 flex items-center gap-2">
                    <Server className="text-blue-600" /> Основные LLM
                </h3>
                
                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">DeepSeek API (Primary)</label>
                        <div className="relative">
                            <input type="password" value="sk-ds-xxxxxxxxxxxx" className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg font-mono text-sm" />
                            <Key className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
                        </div>
                        <p className="text-xs text-green-600 mt-1">✓ Активен</p>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">OpenAI API (Fallback)</label>
                        <div className="relative">
                            <input type="password" value="sk-proj-xxxxxxxxxxxx" className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg font-mono text-sm" />
                            <Key className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
                        </div>
                    </div>
                </div>
            </div>

            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                <h3 className="font-bold text-lg text-slate-900 flex items-center gap-2">
                    <Database className="text-purple-600" /> Медиа и Данные
                </h3>
                
                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Pexels / Unsplash</label>
                        <input type="password" value="xxxxxxxxxxxx" className="w-full p-2 border border-slate-200 rounded-lg font-mono text-sm" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Google News API</label>
                        <input type="password" value="AIzaSyxxxxxxxx" className="w-full p-2 border border-slate-200 rounded-lg font-mono text-sm" />
                    </div>
                </div>
            </div>
            </div>

            {/* DYNAMIC KEYS */}
            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <h3 className="font-bold text-lg text-slate-900 flex items-center gap-2 mb-6">
                    <Puzzle className="text-orange-600" /> Дополнительные сервисы (Custom)
                </h3>
                <div className="space-y-4 mb-6">
                    {customKeys.map(k => (
                        <div key={k.id} className="flex items-center gap-4 p-3 bg-slate-50 rounded-lg border border-slate-100">
                            <span className="font-medium text-slate-700 w-48">{k.serviceName}</span>
                            <code className="flex-1 text-slate-500 font-mono text-sm">••••••••••••••••</code>
                            <button onClick={() => deleteKey(k.id)} className="text-red-500 hover:bg-red-50 p-2 rounded"><Trash2 size={16}/></button>
                        </div>
                    ))}
                    {customKeys.length === 0 && <p className="text-slate-500 text-sm italic">Нет дополнительных ключей.</p>}
                </div>
                
                <div className="flex gap-4 items-end border-t border-slate-100 pt-4">
                    <div className="flex-1">
                        <label className="block text-xs font-medium text-slate-500 mb-1">Название сервиса</label>
                        <input type="text" placeholder="Например: Suno AI, Telegram Bot" value={newKey.serviceName} onChange={e => setNewKey({...newKey, serviceName: e.target.value})} className="w-full p-2 border border-slate-200 rounded-lg" />
                    </div>
                    <div className="flex-1">
                        <label className="block text-xs font-medium text-slate-500 mb-1">API Ключ</label>
                        <input type="text" placeholder="sk-..." value={newKey.key} onChange={e => setNewKey({...newKey, key: e.target.value})} className="w-full p-2 border border-slate-200 rounded-lg" />
                    </div>
                    <button onClick={addCustomKey} className="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-800">Добавить</button>
                </div>
            </div>
        </div>
      )}

      {/* === TAB: TEAM === */}
      {activeTab === 'team' && (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
           <div className="p-6 border-b border-slate-100 flex justify-between items-center">
               <h3 className="font-bold text-lg">Пользователи системы</h3>
               <button onClick={() => setIsAddUserOpen(true)} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium flex items-center gap-2 hover:bg-blue-700">
                   <UserPlus size={16} /> Добавить участника
               </button>
           </div>
           <table className="w-full text-left">
               <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                   <tr>
                       <th className="px-6 py-3">Имя / Email</th>
                       <th className="px-6 py-3">Роль</th>
                       <th className="px-6 py-3">Статус</th>
                       <th className="px-6 py-3 text-right">Действия</th>
                   </tr>
               </thead>
               <tbody className="divide-y divide-slate-100">
                   {users.map(u => (
                       <tr key={u.id} className="hover:bg-slate-50">
                           <td className="px-6 py-4">
                               <div className="font-medium text-slate-900">{u.name}</div>
                               <div className="text-xs text-slate-500">{u.email}</div>
                           </td>
                           <td className="px-6 py-4">
                               <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium capitalize ${u.role === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}`}>
                                   {u.role === 'admin' && <Shield size={10} />} {u.role}
                               </span>
                           </td>
                           <td className="px-6 py-4">
                                <span className={`px-2 py-1 rounded text-xs font-medium ${u.status === 'active' ? 'text-green-600 bg-green-50' : 'text-amber-600 bg-amber-50'}`}>
                                    {u.status === 'active' ? 'Активен' : 'Приглашен'}
                                </span>
                           </td>
                           <td className="px-6 py-4 text-right">
                               <button onClick={() => deleteUser(u.id)} className="text-slate-400 hover:text-red-600 transition-colors"><Trash2 size={18}/></button>
                           </td>
                       </tr>
                   ))}
               </tbody>
           </table>
        </div>
      )}

      {/* MODAL: ADD SOURCE */}
      {isAddSourceOpen && (
        <div className="fixed inset-0 z-[70] bg-black/50 flex items-center justify-center p-4 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in duration-200">
             <div className="p-6 border-b border-slate-100 flex justify-between items-center">
               <h3 className="font-bold text-lg">Добавить источник</h3>
               <button onClick={() => setIsAddSourceOpen(false)} className="p-2 hover:bg-slate-100 rounded-full"><X size={20}/></button>
             </div>
             <div className="p-6 space-y-4">
               <div><label className="block text-sm font-medium mb-1">Название</label><input type="text" className="w-full p-2 border rounded-lg" value={newSource.name} onChange={e => setNewSource({...newSource, name: e.target.value})} /></div>
               <div><label className="block text-sm font-medium mb-1">URL</label><input type="text" className="w-full p-2 border rounded-lg" value={newSource.url} onChange={e => setNewSource({...newSource, url: e.target.value})} /></div>
               <div><label className="block text-sm font-medium mb-1">Категория</label>
                 <select className="w-full p-2 border rounded-lg" value={newSource.category} onChange={e => setNewSource({...newSource, category: e.target.value})}>
                   <option value="official">Official</option><option value="media">Media</option><option value="ukraine">Ukraine</option>
                   <option value="emergency">Emergency</option>
                 </select>
               </div>
             </div>
             <div className="p-6 bg-slate-50 flex justify-end gap-3">
               <button onClick={() => setIsAddSourceOpen(false)} className="px-4 py-2 text-slate-600 hover:bg-white rounded-lg">Отмена</button>
               <button onClick={handleAddSource} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Добавить</button>
             </div>
          </div>
        </div>
      )}

      {/* MODAL: ADD USER */}
      {isAddUserOpen && (
        <div className="fixed inset-0 z-[70] bg-black/50 flex items-center justify-center p-4 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in duration-200">
             <div className="p-6 border-b border-slate-100 flex justify-between items-center">
               <h3 className="font-bold text-lg">Добавить пользователя</h3>
               <button onClick={() => setIsAddUserOpen(false)} className="p-2 hover:bg-slate-100 rounded-full"><X size={20}/></button>
             </div>
             <div className="p-6 space-y-4">
               <div><label className="block text-sm font-medium mb-1">Имя</label><input type="text" className="w-full p-2 border rounded-lg" value={newUser.name} onChange={e => setNewUser({...newUser, name: e.target.value})} /></div>
               <div><label className="block text-sm font-medium mb-1">Email</label><input type="email" className="w-full p-2 border rounded-lg" value={newUser.email} onChange={e => setNewUser({...newUser, email: e.target.value})} /></div>
               <div><label className="block text-sm font-medium mb-1">Роль</label>
                 <select className="w-full p-2 border rounded-lg" value={newUser.role} onChange={e => setNewUser({...newUser, role: e.target.value})}>
                   <option value="editor">Редактор</option><option value="admin">Администратор</option>
                 </select>
               </div>
             </div>
             <div className="p-6 bg-slate-50 flex justify-end gap-3">
               <button onClick={() => setIsAddUserOpen(false)} className="px-4 py-2 text-slate-600 hover:bg-white rounded-lg">Отмена</button>
               <button onClick={handleAddUser} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Пригласить</button>
             </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Settings;
