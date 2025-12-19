
import React, { useState } from 'react';
import { Play, Pause, Download, Mic2, Calendar, CheckCircle } from 'lucide-react';
import { mockPodcasts } from '../mockData';
import { Language } from '../types';

const PodcastView: React.FC = () => {
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentEpisode, setCurrentEpisode] = useState(mockPodcasts[0].id);
  const [currentLang, setCurrentLang] = useState<Language>('de');

  const activeEpisode = mockPodcasts.find(p => p.id === currentEpisode);

  const handlePlay = () => {
    setIsPlaying(!isPlaying);
  };

  const flags: Record<Language, string> = {
    de: '🇩🇪 DE', ua: '🇺🇦 UA', ru: '🇷🇺 RU', en: '🇬🇧 EN'
  };

  return (
    <div className="space-y-6 pb-20">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Аудио новости / Подкаст</h1>
          <p className="text-slate-500">Автоматические ежедневные сводки (TTS) на базе Google Cloud / OpenAI Audio.</p>
        </div>
        <button className="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 flex items-center gap-2 shadow-lg shadow-purple-900/20 transition-all">
          <Mic2 size={18} /> Сгенерировать выпуск
        </button>
      </div>

      {/* Main Player Card */}
      <div className="bg-slate-900 rounded-2xl p-6 md:p-10 text-white shadow-2xl relative overflow-hidden">
        <div className="absolute top-0 right-0 w-64 h-64 bg-purple-600 rounded-full blur-[100px] opacity-30 -translate-y-1/2 translate-x-1/2"></div>
        
        <div className="relative z-10 flex flex-col md:flex-row gap-8 items-center">
           {/* Cover Art */}
           <div className="w-48 h-48 bg-gradient-to-br from-slate-800 to-slate-700 rounded-xl shadow-lg flex items-center justify-center shrink-0 border border-slate-600">
             <Mic2 size={64} className="text-slate-400" />
           </div>

           {/* Controls */}
           <div className="flex-1 w-full space-y-6">
             <div>
               <h2 className="text-2xl font-bold">{activeEpisode?.title}</h2>
               <p className="text-slate-400 flex items-center gap-2 mt-1">
                 <Calendar size={14} /> {activeEpisode?.date} • {activeEpisode?.duration}
               </p>
             </div>

             {/* Language Selector for Audio */}
             <div className="flex gap-2">
               {Object.keys(flags).map(lang => (
                 <button 
                   key={lang}
                   onClick={() => setCurrentLang(lang as Language)}
                   className={`px-3 py-1 rounded-lg text-xs font-bold transition-colors ${currentLang === lang ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700'}`}
                 >
                   {flags[lang as Language]}
                 </button>
               ))}
             </div>

             {/* Progress Bar (Visual Only) */}
             <div className="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
               <div className="w-1/3 h-full bg-purple-500 rounded-full"></div>
             </div>

             <div className="flex items-center justify-between">
               <div className="text-xs font-mono text-slate-400">04:20 / {activeEpisode?.duration}</div>
               <div className="flex items-center gap-4">
                 <button className="p-3 rounded-full bg-white text-slate-900 hover:bg-purple-50 transition-colors" onClick={handlePlay}>
                   {isPlaying ? <Pause fill="currentColor" /> : <Play fill="currentColor" className="ml-1" />}
                 </button>
                 <button className="p-2 text-slate-400 hover:text-white"><Download size={20} /></button>
               </div>
             </div>
           </div>
        </div>
      </div>

      {/* Episode List */}
      <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div className="p-4 border-b border-slate-100 font-semibold text-slate-700">Последние выпуски</div>
        <div className="divide-y divide-slate-100">
          {mockPodcasts.map(pod => (
            <div 
              key={pod.id} 
              onClick={() => setCurrentEpisode(pod.id)}
              className={`p-4 flex items-center justify-between hover:bg-slate-50 cursor-pointer transition-colors ${currentEpisode === pod.id ? 'bg-blue-50/50' : ''}`}
            >
              <div className="flex items-center gap-4">
                <div className={`w-10 h-10 rounded-full flex items-center justify-center ${currentEpisode === pod.id ? 'bg-purple-100 text-purple-600' : 'bg-slate-100 text-slate-500'}`}>
                  {currentEpisode === pod.id && isPlaying ? <div className="w-3 h-3 bg-purple-600 rounded-full animate-pulse" /> : <Play size={16} fill="currentColor" />}
                </div>
                <div>
                  <h4 className={`font-medium ${currentEpisode === pod.id ? 'text-purple-700' : 'text-slate-900'}`}>{pod.title}</h4>
                  <p className="text-xs text-slate-500">{pod.date} • {pod.topics.join(', ')}</p>
                </div>
              </div>
              <div className="flex items-center gap-3">
                 <span className="px-2 py-1 bg-green-50 text-green-700 text-xs rounded border border-green-100 flex items-center gap-1">
                    <CheckCircle size={10} /> Ready
                 </span>
                 <span className="text-sm font-mono text-slate-400">{pod.duration}</span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default PodcastView;
