
import React, { useState } from 'react';
import { MessageSquare, ThumbsUp, Trash2, AlertTriangle, CheckCircle } from 'lucide-react';

const Moderation: React.FC = () => {
  const [comments, setComments] = useState([
    { id: 1, user: 'Ivan K.', text: 'Дякую за цю інформацію! Дуже корисно. Підкажіть, а де саме це знаходиться?', sentiment: 'positive', date: '10 мин назад', toxic: false },
    { id: 2, user: 'Max M.', text: 'Das ist alles Quatsch! Fake News! Вы все врете, уезжайте отсюда!', sentiment: 'negative', date: '15 мин назад', toxic: true },
    { id: 3, user: 'Olena P.', text: 'А куди звертатися за допомогою?', sentiment: 'neutral', date: '1 час назад', toxic: false },
    { id: 4, user: 'Bot_231', text: 'Best crypto investment here -> link...', sentiment: 'spam', date: '2 часа назад', toxic: true },
  ]);

  const handleApprove = (id: number) => {
    setComments(comments.filter(c => c.id !== id));
  };

  const handleDelete = (id: number) => {
    setComments(comments.filter(c => c.id !== id));
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-900">Модерация комментариев</h1>
        <div className="flex gap-2">
            <span className="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium border border-red-200">{comments.filter(c => c.toxic).length} Токсичных</span>
            <span className="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium border border-blue-200">{comments.length} Новых</span>
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        {comments.length === 0 ? (
            <div className="p-12 text-center text-slate-500">
                <CheckCircle size={48} className="mx-auto text-green-500 mb-4" />
                <p className="text-lg font-medium">Все чисто!</p>
                <p>Нет новых комментариев для проверки.</p>
            </div>
        ) : (
        <div className="divide-y divide-slate-100">
            {comments.map(comment => (
                <div key={comment.id} className="p-6 flex gap-4 hover:bg-slate-50 transition-colors group">
                    <div className="w-10 h-10 bg-gradient-to-br from-slate-200 to-slate-300 rounded-full flex items-center justify-center font-bold text-slate-600 shrink-0 shadow-sm">
                        {comment.user.charAt(0)}
                    </div>
                    <div className="flex-1">
                        <div className="flex items-center justify-between mb-1">
                            <h4 className="font-semibold text-slate-900">{comment.user}</h4>
                            <span className="text-xs text-slate-400">{comment.date}</span>
                        </div>
                        <p className="text-slate-700 mb-3 leading-relaxed">{comment.text}</p>
                        
                        {comment.toxic && (
                            <div className="inline-flex items-center gap-1 text-xs text-red-700 bg-red-50 px-2 py-1 rounded mb-3 border border-red-100">
                                <AlertTriangle size={12} /> Подозрение на спам/токсичность
                            </div>
                        )}

                        <div className="flex items-center gap-3 pt-2">
                            <button 
                                onClick={() => handleApprove(comment.id)}
                                className="flex items-center gap-1 px-4 py-1.5 bg-green-50 text-green-700 rounded-lg text-xs font-medium hover:bg-green-100 border border-green-100 transition-colors"
                            >
                                <ThumbsUp size={14} /> Одобрить
                            </button>
                            <button 
                                className="flex items-center gap-1 px-4 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-xs font-medium hover:bg-slate-200 border border-slate-200 transition-colors"
                            >
                                <MessageSquare size={14} /> Ответить
                            </button>
                            <button 
                                onClick={() => handleDelete(comment.id)}
                                className="flex items-center gap-1 px-4 py-1.5 hover:bg-red-50 text-slate-400 hover:text-red-600 rounded-lg text-xs font-medium ml-auto border border-transparent hover:border-red-100 transition-colors"
                            >
                                <Trash2 size={14} /> Удалить
                            </button>
                        </div>
                    </div>
                </div>
            ))}
        </div>
        )}
      </div>
    </div>
  );
};

export default Moderation;
