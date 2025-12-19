
import React, { useState } from 'react';
import { Save, Send, Image as ImageIcon, Link as LinkIcon, AlertCircle, UploadCloud } from 'lucide-react';

const CreateArticle: React.FC = () => {
  const [mode, setMode] = useState<'manual' | 'url'>('manual');
  const [image, setImage] = useState<string | null>(null);

  const handleImageUpload = () => {
    // Simulation
    setImage('https://picsum.photos/800/400');
  };

  return (
    <div className="max-w-4xl mx-auto pb-20">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Создать новый материал</h1>
        <p className="text-slate-500">Напишите вручную или вставьте ссылку на источник для обработки ИИ.</p>
      </div>

      {/* Mode Selection */}
      <div className="bg-white p-1 rounded-xl border border-slate-200 inline-flex mb-8 shadow-sm">
        <button 
          onClick={() => setMode('manual')}
          className={`px-6 py-2 rounded-lg text-sm font-medium transition-all ${mode === 'manual' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50'}`}
        >
          Вручную
        </button>
        <button 
          onClick={() => setMode('url')}
          className={`px-6 py-2 rounded-lg text-sm font-medium transition-all ${mode === 'url' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50'}`}
        >
          Из URL источника
        </button>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        {mode === 'url' ? (
           <div className="space-y-6">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">URL Источника</label>
                <div className="flex gap-2">
                    <div className="relative flex-1">
                        <LinkIcon className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                        <input 
                            type="text" 
                            placeholder="https://www.sueddeutsche.de/muenchen/..." 
                            className="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition-all"
                        />
                    </div>
                    <button className="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                        <Send size={18} /> Обработать
                    </button>
                </div>
                <p className="mt-2 text-xs text-slate-500 flex items-center gap-1">
                    <AlertCircle size={12} /> AI извлечет контент, проверит факты и создаст черновики на 4 языках.
                </p>
              </div>
           </div>
        ) : (
            <div className="space-y-8">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-2">
                        <label className="block text-sm font-medium text-slate-700">Исходный язык</label>
                        <select className="w-full p-3 bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-blue-500">
                            <option value="de">Немецкий (DE)</option>
                            <option value="en">Английский (EN)</option>
                        </select>
                    </div>
                    <div className="space-y-2">
                        <label className="block text-sm font-medium text-slate-700">Целевые языки</label>
                        <div className="flex gap-3 pt-2">
                            {['UA', 'RU', 'EN'].map(lang => (
                                <label key={lang} className="flex items-center gap-2 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-md cursor-pointer hover:bg-slate-100">
                                    <input type="checkbox" defaultChecked className="rounded text-blue-600" />
                                    <span className="text-sm font-medium text-slate-600">{lang}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="space-y-2">
                    <label className="block text-sm font-medium text-slate-700">Обложка новости</label>
                    <div 
                        onClick={handleImageUpload}
                        className="w-full h-48 border-2 border-dashed border-slate-300 rounded-xl flex flex-col items-center justify-center bg-slate-50 hover:bg-slate-100 cursor-pointer transition-colors overflow-hidden relative group"
                    >
                        {image ? (
                            <>
                                <img src={image} alt="Preview" className="w-full h-full object-cover" />
                                <div className="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <p className="text-white font-medium">Нажмите чтобы заменить</p>
                                </div>
                            </>
                        ) : (
                            <>
                                <UploadCloud size={32} className="text-slate-400 mb-2" />
                                <span className="text-sm text-slate-500 font-medium">Нажмите для загрузки или перетащите</span>
                                <span className="text-xs text-slate-400 mt-1">JPG, PNG до 5MB</span>
                            </>
                        )}
                    </div>
                </div>

                <div className="space-y-2">
                    <label className="block text-sm font-medium text-slate-700">
                        Заголовок (Немецкий) <span className="text-slate-400 font-normal ml-2">0 / 60</span>
                    </label>
                    <input type="text" className="w-full p-3 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Введите яркий заголовок..." />
                </div>

                <div className="space-y-2">
                    <label className="block text-sm font-medium text-slate-700">
                        Лид (Вступление) <span className="text-slate-400 font-normal ml-2">0 / 300</span>
                    </label>
                    <textarea className="w-full p-3 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none h-24 resize-none" placeholder="Краткая суть новости..." />
                </div>

                <div className="space-y-2">
                    <label className="block text-sm font-medium text-slate-700">Контент</label>
                    <div className="w-full border border-slate-200 rounded-lg overflow-hidden">
                        <div className="bg-slate-50 border-b border-slate-200 p-2 flex gap-2">
                            <button className="p-1.5 hover:bg-slate-200 rounded text-slate-600 font-bold">B</button>
                            <button className="p-1.5 hover:bg-slate-200 rounded text-slate-600 italic">I</button>
                            <div className="w-px h-6 bg-slate-300 mx-1" />
                            <button className="p-1.5 hover:bg-slate-200 rounded text-slate-600 flex items-center gap-1 text-xs">
                                <ImageIcon size={14} /> Медиа
                            </button>
                        </div>
                        <textarea className="w-full p-4 h-64 outline-none resize-none" placeholder="Напишите новость здесь..." />
                    </div>
                </div>

                <div className="flex items-center justify-end gap-4 pt-4 border-t border-slate-100">
                    <button className="px-6 py-2.5 text-slate-600 font-medium hover:bg-slate-50 rounded-lg transition-colors">
                        Отмена
                    </button>
                    <button className="px-6 py-2.5 border border-slate-200 text-slate-700 font-medium rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2">
                        <Save size={18} /> Сохранить черновик
                    </button>
                    <button className="px-6 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 shadow-lg shadow-blue-900/20">
                        <Send size={18} /> Обработать AI
                    </button>
                </div>
            </div>
        )}
      </div>
    </div>
  );
};

export default CreateArticle;
