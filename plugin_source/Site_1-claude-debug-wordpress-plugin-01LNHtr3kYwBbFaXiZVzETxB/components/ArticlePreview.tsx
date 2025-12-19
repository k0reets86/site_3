
import React, { useState } from 'react';
import { Article, Language } from '../types';
import { 
  X, Check, ChevronRight, Globe2, Image as ImageIcon, 
  Volume2, BarChart2, Calendar, ShieldCheck, RotateCcw, 
  Share2, Edit2, Save, Clock
} from 'lucide-react';

interface ArticlePreviewProps {
  article: Article | null;
  onClose: () => void;
}

const ArticlePreview: React.FC<ArticlePreviewProps> = ({ article, onClose }) => {
  const [activeLang, setActiveLang] = useState<Language>('de');
  const [isEditing, setIsEditing] = useState(false);
  const [isScheduleModalOpen, setIsScheduleModalOpen] = useState(false);
  const [scheduledDate, setScheduledDate] = useState('');
  const [scheduledTime, setScheduledTime] = useState('');

  if (!article) return null;

  const flags: Record<Language, string> = {
    de: '🇩🇪 Deutsch',
    ua: '🇺🇦 Українська',
    ru: '🇷🇺 Русский',
    en: '🇬🇧 English'
  };

  const handleSchedule = () => {
    alert(`Материал запланирован на ${scheduledDate} в ${scheduledTime}`);
    setIsScheduleModalOpen(false);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-end bg-slate-900/50 backdrop-blur-sm animate-in fade-in duration-200">
      
      {/* Main Modal */}
      <div className="w-full max-w-6xl h-full md:h-[95vh] md:m-4 bg-white md:rounded-2xl shadow-2xl flex flex-col overflow-hidden animate-in slide-in-from-right duration-300">
        
        {/* Header */}
        <div className="h-16 border-b border-slate-200 flex items-center justify-between px-4 md:px-6 bg-white shrink-0">
          <div className="flex items-center gap-3 md:gap-4">
            <button onClick={onClose} className="p-2 hover:bg-slate-100 rounded-lg text-slate-500">
              <X size={20} />
            </button>
            <div>
              <h2 className="font-semibold text-slate-900 flex flex-col md:flex-row md:items-center gap-1 md:gap-2 text-sm md:text-base">
                <span className="truncate max-w-[150px] md:max-w-none">#{article.id}</span>
                <span className={`px-2 py-0.5 text-[10px] md:text-xs rounded-full w-fit ${
                  article.status === 'PENDING_OK' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'
                }`}>
                  {article.status === 'PENDING_OK' ? 'ПРОВЕРКА' : 'АВТО'}
                </span>
              </h2>
            </div>
          </div>
          
          <div className="flex items-center gap-2 md:gap-3">
             <button 
                onClick={() => setIsEditing(!isEditing)}
                className={`px-3 py-2 border rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${isEditing ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'}`}
             >
                {isEditing ? <Save size={16} /> : <Edit2 size={16} />}
                <span className="hidden md:inline">{isEditing ? 'Сохранить' : 'Править'}</span>
             </button>
             <button onClick={() => alert('Опубликовано!')} className="px-3 md:px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors flex items-center gap-2 shadow-lg shadow-slate-900/20">
                <Check size={16} /> 
                <span className="hidden md:inline">Опубликовать</span>
             </button>
          </div>
        </div>

        {/* Main Content Grid */}
        <div className="flex-1 grid grid-cols-1 lg:grid-cols-12 overflow-hidden">
          
          {/* Left: Content Preview (Scrollable) */}
          <div className="col-span-1 lg:col-span-8 overflow-y-auto p-4 md:p-8 bg-slate-50 border-r border-slate-200">
            
            {/* Language Tabs */}
            <div className="flex items-center gap-2 mb-6 overflow-x-auto pb-2 hide-scrollbar">
                {(Object.keys(flags) as Language[]).map(lang => (
                    <button
                        key={lang}
                        onClick={() => setActiveLang(lang)}
                        className={`px-4 py-2 rounded-full text-sm font-medium transition-all whitespace-nowrap ${
                            activeLang === lang 
                                ? 'bg-white text-blue-600 shadow-md ring-1 ring-slate-200' 
                                : 'text-slate-500 hover:bg-white/50 hover:text-slate-700'
                        }`}
                    >
                        {flags[lang]}
                    </button>
                ))}
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4 md:p-8 max-w-3xl mx-auto">
                {/* Featured Image */}
                <div className="relative aspect-video bg-slate-100 rounded-lg overflow-hidden mb-6 group">
                    <img src={article.imageUrl} alt="Article cover" className="w-full h-full object-cover" />
                    <div className={`absolute inset-0 bg-black/40 flex items-center justify-center transition-opacity ${isEditing ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'}`}>
                        <button className="px-4 py-2 bg-white text-slate-900 rounded-lg shadow-lg font-medium flex items-center gap-2 hover:bg-slate-100">
                            <ImageIcon size={18} /> Заменить фото
                        </button>
                    </div>
                </div>

                {/* Title & Content */}
                <div className="space-y-6">
                    <div>
                        {isEditing ? (
                           <textarea 
                              className="w-full text-2xl md:text-3xl font-bold text-slate-900 bg-slate-50 border-b-2 border-blue-500 focus:outline-none resize-none overflow-hidden"
                              defaultValue={article.titles[activeLang]}
                              rows={2}
                           />
                        ) : (
                           <h1 className="text-2xl md:text-3xl font-bold text-slate-900 leading-tight">
                               {article.titles[activeLang]}
                           </h1>
                        )}
                    </div>

                    {article.content && (
                        <>
                        <div className={`text-base md:text-lg font-medium text-slate-700 leading-relaxed border-l-4 border-blue-500 pl-4 italic ${isEditing ? 'bg-slate-50 p-2 rounded' : ''}`} contentEditable={isEditing}>
                            {article.content.lead[activeLang]}
                        </div>
                        <div 
                            className={`prose prose-slate max-w-none text-slate-600 ${isEditing ? 'bg-slate-50 p-4 rounded border border-dashed border-slate-300 min-h-[300px]' : ''}`}
                            contentEditable={isEditing}
                            dangerouslySetInnerHTML={{ __html: article.content.body[activeLang] }} 
                        />
                        </>
                    )}
                </div>
            </div>
          </div>

          {/* Right: Metadata Sidebar (Scrollable) */}
          <div className="col-span-1 lg:col-span-4 overflow-y-auto bg-white p-6 space-y-6 border-t lg:border-t-0 border-slate-200">
            
            {/* Status Card */}
            <div className="p-4 rounded-xl bg-slate-50 border border-slate-100 space-y-3">
                <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Анализ</span>
                    <span className="text-green-600 text-xs font-bold bg-green-50 px-2 py-1 rounded border border-green-100">Высокое качество</span>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1">
                        <span className="text-xs text-slate-400">Факт-чекинг</span>
                        <div className="flex items-center gap-1 text-sm font-medium text-slate-700">
                            <ShieldCheck size={16} className="text-green-500" />
                            {Math.round(article.factCheckScore * 100)}%
                        </div>
                    </div>
                    <div className="space-y-1">
                        <span className="text-xs text-slate-400">Прогноз CTR</span>
                        <div className="flex items-center gap-1 text-sm font-medium text-slate-700">
                            <BarChart2 size={16} className="text-blue-500" />
                            {article.predictedCtr}%
                        </div>
                    </div>
                </div>
            </div>

            {/* Publishing */}
            <div className="space-y-3 pt-4 border-t border-slate-100">
                 <h3 className="text-sm font-semibold text-slate-900">Дистрибуция</h3>
                 <div className="space-y-2">
                    {['WordPress', 'Facebook', 'Instagram', 'Telegram'].map(platform => (
                        <label key={platform} className="flex items-center gap-2 p-2 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" defaultChecked className="rounded text-blue-600 focus:ring-blue-500" />
                            <span className="text-sm text-slate-700">{platform}</span>
                        </label>
                    ))}
                 </div>
                 <button 
                    onClick={() => setIsScheduleModalOpen(true)}
                    className="w-full py-3 bg-white border border-slate-200 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 flex items-center justify-center gap-2 shadow-sm"
                 >
                    <Calendar size={16} /> Запланировать публикацию
                 </button>
            </div>

          </div>
        </div>
      </div>

      {/* Schedule Modal */}
      {isScheduleModalOpen && (
        <div className="fixed inset-0 z-[70] bg-black/50 flex items-center justify-center p-4 backdrop-blur-sm">
            <div className="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm animate-in zoom-in duration-200">
                <h3 className="text-lg font-bold mb-4">Выбор даты и времени</h3>
                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Дата</label>
                        <input type="date" onChange={(e) => setScheduledDate(e.target.value)} className="w-full p-2 border border-slate-200 rounded-lg" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Время</label>
                        <input type="time" onChange={(e) => setScheduledTime(e.target.value)} className="w-full p-2 border border-slate-200 rounded-lg" />
                    </div>
                </div>
                <div className="flex gap-3 mt-6 justify-end">
                    <button onClick={() => setIsScheduleModalOpen(false)} className="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Отмена</button>
                    <button onClick={handleSchedule} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">Сохранить</button>
                </div>
            </div>
        </div>
      )}

    </div>
  );
};

export default ArticlePreview;
