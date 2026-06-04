// public/assets/js/comments.js

// Variáveis globais (certifique-se de que estas são injetadas do PHP no seu HTML, antes deste script)
// Exemplo no seu ficheiro .php (index.php, post.php, profile.php, album.php):
// <script>
//     const BASE_URL = "<?php echo BASE_URL; ?>";
//     const UPLOAD_URL = "<?php echo UPLOAD_URL; ?>";
//     const CURRENT_USER_ID = <?php echo is_logged_in() ? get_current_user_id() : 'null'; ?>;
//     const CURRENT_USER_PROFILE_PICTURE = "<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png'); ?>";
//     // Para post.php/album.php, onde há um único item principal:
//     const POST_OWNER_ID = <?php echo htmlspecialchars($feed_item['user_id'] ?? 'null'); ?>;
//     const IS_POST_OWNER = (CURRENT_USER_ID !== null && POST_OWNER_ID !== null && CURRENT_USER_ID == POST_OWNER_ID);
//     // Para index.php (feed geral), onde há múltiplos posts, IS_POST_OWNER será avaliado por item no PHP e não é uma constante global aqui.
//     // A função renderCommentElement no JS receberá isPostOwner como parâmetro.
// </script>


document.addEventListener('DOMContentLoaded', function() {
    // Referências a elementos que podem existir na página (para contagens globais)
    const commentsList = document.querySelector('.comments-list'); // Lista principal de comentários
    const commentCountDisplay = document.querySelector('.comment-count-display'); // Contagem no rodapé do post
    const commentCountSection = document.querySelector('.comments-section .comment-count'); // Contagem no cabeçalho da seção de comentários

    // Helper para criar elementos HTML
    function createElement(tag, classes = [], attributes = {}, content = '') {
        const element = document.createElement(tag);
        if (classes.length > 0) {
            const validClasses = classes.filter(cls => cls.trim() !== '');
            if (validClasses.length > 0) {
                element.classList.add(...validClasses);
            }
        }
        for (const key in attributes) {
            element.setAttribute(key, attributes[key]);
        }
        if (content) {
            element.innerHTML = content;
        }
        return element;
    }

    // Helper para converter quebras de linha em <br> (como nl2br do PHP)
    function nl2br(str) {
        return str.replace(/(?:\r\n|\r|\n)/g, '<br>');
    }

    // Helper para escapar HTML (como htmlspecialchars do PHP)
    function htmlspecialchars(str) {
        if (typeof str !== 'string') return str;
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    window.toggleCommentOptions = (event, commentId) => {
        event.stopPropagation();
        const menu = document.getElementById(`comment-options-${commentId}`);
        const allMenus = document.querySelectorAll('.comment-options-menu');
        
        allMenus.forEach(m => {
            if (m.id !== `comment-options-${commentId}`) m.style.display = 'none';
        });
        
        if (menu) {
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
            
            const closeMenu = (e) => {
                if (!menu.contains(e.target)) {
                    menu.style.display = 'none';
                    document.removeEventListener('click', closeMenu);
                }
            };
            document.addEventListener('click', closeMenu);
        }
    };

    /**
     * Constrói o elemento HTML para um único comentário.
     * Deve espelhar a saída da função PHP display_comments.
     * @param {Object} comment Objeto de comentário com dados.
     * @param {number} level Nível de aninhamento do comentário.
     * @param {number|null} currentUserId ID do utilizador logado.
     * @param {boolean} isPostOwner Indica se o utilizador logado é o dono do post principal.
     * @returns {HTMLElement} O elemento <li> HTML do comentário.
     */
      function renderCommentElement(comment, level, currentUserId, isPostOwner) {
        const isReply = level > 0;
        const isCommentOwner = currentUserId !== null && comment.user_id == currentUserId;
        const profilePictureUrl = UPLOAD_URL + htmlspecialchars(comment.profile_picture || 'profiles/default_profile.png');

        const commentItem = createElement('li', ['comment-item'], {
            'data-comment-id': comment.id
        });

        const profilePicture = createElement('img', ['comment-avatar'], {
            src: profilePictureUrl,
            alt: `Foto de perfil de ${htmlspecialchars(comment.username)}`
        });

        const commentBody = createElement('div', ['comment-body']);
        const commentTextWrapper = createElement('div', ['comment-text-wrapper']);
        const commentHeader = createElement('div', ['comment-header']);
        
        const usernameLink = createElement('a', ['comment-author'], {
            href: `${BASE_URL}profile.php?id=${comment.user_id}`
        }, htmlspecialchars(comment.username));
        
        const optionsWrapper = createElement('div', ['comment-actions-dropdown']);
        if (isCommentOwner || isPostOwner) {
            const optionsBtn = createElement('button', ['dropdown-toggle'], {'aria-label': 'Opções'}, '&#x22EE;');
            const optionsMenu = createElement('div', ['dropdown-menu']);

            if (isCommentOwner) {
                const editBtn = createElement('button', ['edit-comment-btn'], {
                    'data-comment-id': comment.id,
                    'data-content': htmlspecialchars(comment.content)
                }, 'Editar');
                optionsMenu.appendChild(editBtn);
            }
            if (isCommentOwner || isPostOwner) {
                const deleteBtn = createElement('button', ['delete-comment-btn'], {
                    'data-comment-id': comment.id,
                    'data-feed-item-id': comment.feed_item_id
                }, 'Apagar');
                optionsMenu.appendChild(deleteBtn);
            }
            optionsWrapper.append(optionsBtn, optionsMenu);
        }
        
        commentHeader.append(usernameLink, optionsWrapper);
        commentTextWrapper.appendChild(commentHeader);

        const commentText = createElement('div', ['comment-text'], {}, `<p>${nl2br(htmlspecialchars(comment.content))}</p>`);
        commentTextWrapper.appendChild(commentText);
        commentBody.appendChild(commentTextWrapper);

        const commentActions = createElement('div', ['comment-actions']);
        const commentTimestamp = createElement('span', ['comment-time'], {}, htmlspecialchars(comment.formatted_created_at || 'agora mesmo'));
        
        const likeBtn = createElement('button', ['btn-comment-like', (comment.user_vote === 'like' ? 'active' : '')], {
            'data-comment-id': comment.id,
            'data-vote-type': 'like'
        }, `Gosto <span class="comment-likes-count">${comment.likes_count || 0}</span>`);

        const dislikeBtn = createElement('button', ['btn-comment-dislike', (comment.user_vote === 'dislike' ? 'active' : '')], {
            'data-comment-id': comment.id,
            'data-vote-type': 'dislike'
        }, `Não gosto`);
        
        commentActions.appendChild(commentTimestamp);
        commentActions.appendChild(likeBtn);
        commentActions.appendChild(dislikeBtn);

        if (currentUserId) {
            const replyBtn = createElement('button', ['btn-reply-comment'], {
                'data-comment-id': comment.id,
                'data-comment-author-username': htmlspecialchars(comment.username)
            }, 'Responder');
            commentActions.appendChild(replyBtn);
        }
        commentBody.appendChild(commentActions);

        const replyFormContainer = createElement('div', ['reply-form-container'], {
            id: `replyFormContainer-${comment.id}`,
            style: 'display: none;'
        });
        commentContent.appendChild(replyFormContainer);

        if (comment.replies && comment.replies.length > 0) {
            const repliesList = createElement('ul', ['comment-list', 'comment-replies'], {style: 'display: none;'});
            comment.replies.forEach(reply => {
                repliesList.appendChild(renderCommentElement(reply, level + 1, currentUserId, isPostOwner));
            });
            commentContent.appendChild(repliesList);

            const toggleBtn = createElement('button', ['btn-toggle-replies'], {
                'data-comment-id': comment.id
            }, `Ver ${comment.replies.length} resposta${comment.replies.length > 1 ? 's' : ''}`);
            commentContent.appendChild(toggleBtn);
        }

        commentItem.append(avatarContainer, commentContent);
        return commentItem;
    }


    /**
     * Atualiza os botões de like/dislike de um post na UI.
     * @param {string} feedItemId O ID do item do feed.
     * @param {number} newLikes Nova contagem de likes.
     * @param {number} newDislikes Nova contagem de dislikes.
     * @param {string|null} userVote O voto atual do utilizador ('like', 'dislike', ou null).
     */
    function updatePostLikeButtons(feedItemId, newLikes, newDislikes, userVote) {
        // Selecionar todos os cartões que podem ter este feedItemId (útil para modal e feed simultâneos)
        const postCards = document.querySelectorAll(`article[data-feed-item-id="${feedItemId}"]`);
        
        if (postCards.length === 0) {
            console.error('Nenhum cartão de postagem encontrado para feed_item_id:', feedItemId);
            return;
        }

        postCards.forEach(postCard => {
            // Seletores mais flexíveis para encontrar os botões de like/dislike
            const likeBtn = postCard.querySelector('.btn-like[data-action="like"]') || postCard.querySelector('.btn-like');
            const dislikeBtn = postCard.querySelector('.btn-dislike[data-action="dislike"]') || postCard.querySelector('.btn-dislike');
            
            if (likeBtn) {
                const countSpan = likeBtn.querySelector('.likes-count');
                if (countSpan) countSpan.textContent = newLikes;
                
                if (userVote === 'like') {
                    likeBtn.classList.add('active');
                } else {
                    likeBtn.classList.remove('active');
                }
            }
            
            if (dislikeBtn) {
                const countSpan = dislikeBtn.querySelector('.dislikes-count');
                if (countSpan) countSpan.textContent = newDislikes;
                
                if (userVote === 'dislike') {
                    dislikeBtn.classList.add('active');
                } else {
                    dislikeBtn.classList.remove('active');
                }
            }
        });
    }

    /**
     * Lida com o clique nos botões de like/dislike de posts.
     * @param {Event} event O evento de clique.
     */
    function handlePostLikeDislikeClick(event) {
        if (CURRENT_USER_ID === null) {
            alert('Você precisa estar logado para interagir com uma publicação.');
            window.location.href = BASE_URL + 'login.php';
            return;
        }

        const button = event.currentTarget;
        const feedItemId = button.getAttribute('data-feed-item-id');
        const action = button.getAttribute('data-action'); // 'like' ou 'dislike'

        if (!feedItemId || !action) {
            console.error('Dados ausentes para a interação do post:', { feedItemId, action });
            return;
        }

        const formData = new FormData();
        formData.append('feed_item_id', feedItemId);
        formData.append('action', action);

        fetch(BASE_URL + 'process_like.php', { // Aponta para process_like.php
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errorData => {
                    throw new Error(errorData.message || 'Erro de rede ou servidor.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updatePostLikeButtons(feedItemId, data.likes, data.dislikes, data.user_vote);
            } else {
                alert('Erro ao processar sua requisição: ' + (data.message || 'Erro desconhecido.'));
                console.error('Erro na resposta do servidor:', data.message);
            }
        })
        .catch(error => {
            console.error('Falha na requisição Fetch para post like/dislike:', error);
            alert('Ocorreu um erro ao tentar interagir com a publicação. Tente novamente.');
        });
    }


    // Função para inicializar/re-inicializar listeners para elementos de comentário e post
    window.initCommentAndPostEventListeners = function(parentElement = document) {
        // Auto-ajuste de altura para textareas
        parentElement.querySelectorAll('.comment-input-wrapper textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });

        // --- Comentários ---
        // Dropdown de Ações do Comentário
        parentElement.querySelectorAll('.comment-actions-dropdown .dropdown-toggle').forEach(button => {
            button.removeEventListener('click', handleDropdownToggle);
            button.addEventListener('click', handleDropdownToggle);
        });

        // Botões de Like/Dislike de Comentários
        parentElement.querySelectorAll('.btn-comment-like, .btn-comment-dislike').forEach(button => {
            button.removeEventListener('click', handleCommentVote);
            button.addEventListener('click', handleCommentVote);
        });

        // Botões de Responder
        parentElement.querySelectorAll('.btn-reply-comment').forEach(button => {
            button.removeEventListener('click', handleReplyClick);
            button.addEventListener('click', handleReplyClick);
        });

        // Botões de Editar
        parentElement.querySelectorAll('.edit-comment-btn').forEach(button => {
            button.removeEventListener('click', handleEditClick);
            button.addEventListener('click', handleEditClick);
        });

        // Botões de Apagar
        parentElement.querySelectorAll('.delete-comment-btn').forEach(button => {
            button.removeEventListener('click', handleDeleteClick);
            button.addEventListener('click', handleDeleteClick);
        });

        // Botões de Mostrar/Esconder Respostas
        parentElement.querySelectorAll('.btn-toggle-replies').forEach(button => {
            button.removeEventListener('click', handleToggleReplies);
            button.addEventListener('click', handleToggleReplies);
        });

        // Formulários de resposta/edição (criados dinamicamente)
        parentElement.querySelectorAll('.comment-form.reply-form, .comment-form.edit-form').forEach(form => {
            form.removeEventListener('submit', handleFormSubmit); // Evita duplicidade
            form.addEventListener('submit', handleFormSubmit);
        });
        parentElement.querySelectorAll('.cancel-reply-btn, .cancel-edit-btn').forEach(button => {
            button.removeEventListener('click', handleCancelForm);
            button.addEventListener('click', handleCancelForm);
        });

        // --- Posts (Likes/Dislikes) ---
        // Seleciona todos os botões de like e dislike de posts na página
        // E anexa listeners a eles
        parentElement.querySelectorAll('.btn-like[data-action="like"], .btn-dislike[data-action="dislike"], .btn-like, .btn-dislike').forEach(button => {
            if (button.hasAttribute('data-feed-item-id')) {
                button.removeEventListener('click', handlePostLikeDislikeClick); // Remove para evitar duplicidade
                button.addEventListener('click', handlePostLikeDislikeClick);
            }
        });
    }

    // Handlers separados para serem re-usáveis e removíveis
    function handleDropdownToggle(event) {
        const dropdown = this.closest('.comment-actions-dropdown');
        dropdown.classList.toggle('active');
        event.stopPropagation();
    }

    function handleCommentVote(event) {
        if (CURRENT_USER_ID === null) {
            alert('Você precisa estar logado para votar em comentários.');
            window.location.href = BASE_URL + 'login.php';
            return;
        }

        const button = event.target.closest('button');
        const commentId = button.dataset.commentId;
        const voteType = button.dataset.voteType; // Usar data-vote-type

        if (!commentId || !voteType) {
            console.error('Dados de voto de comentário incompletos.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'vote_comment');
        formData.append('comment_id', commentId);
        formData.append('vote_type', voteType);

        // Adicionar o feed_item_id ao FormData para a notificação no backend
        // Obter o feed_item_id do elemento pai mais próximo que o contém
        const commentItem = button.closest('.comment-item');
        const feedItemId = commentItem ? commentItem.closest('.post-card')?.dataset.feedItemId : null;
        if (feedItemId) {
            formData.append('feed_item_id', feedItemId);
        } else {
            console.warn('Feed Item ID não encontrado para o voto do comentário. Notificação pode não ter link completo.');
        }


        fetch(BASE_URL + 'process_comment.php', { // Aponta para process_comment.php
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errorData => {
                    throw new Error(errorData.message || 'Erro de rede ou servidor.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const commentItem = button.closest('.comment-item');
                if (!commentItem) return;

                const likeButton = commentItem.querySelector('.btn-comment-like');
                const dislikeButton = commentItem.querySelector('.btn-comment-dislike');
                
                if (likeButton) likeButton.querySelector('.comment-likes-count').textContent = data.likes;
                if (dislikeButton) dislikeButton.querySelector('.comment-dislikes-count').textContent = data.dislikes;

                // Atualiza o estado ativo dos botões
                likeButton.classList.remove('active');
                dislikeButton.classList.remove('active');
                if (data.user_vote === 'like') {
                    likeButton.classList.add('active');
                } else if (data.user_vote === 'dislike') {
                    dislikeButton.classList.add('active');
                }
            } else {
                alert('Erro ao processar o voto: ' + (data.message || 'Erro desconhecido.'));
            }
        })
        .catch(error => {
            console.error('Erro na requisição AJAX de voto:', error);
            alert('Ocorreu um erro ao conectar com o servidor para votar.');
        });
    }

     function handleReplyClick(e) {
        const btn = e.target.closest('.btn-reply-comment');
        if (!btn) return;
        const commentId = btn.dataset.commentId;
        const container = document.getElementById(`replyFormContainer-${commentId}`);
        if (!container) return;

        if (container.style.display === 'block') {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        const feedItemId = btn.closest('.comment-section-full').dataset.feedItemId;
        container.innerHTML = `
            <form action="${BASE_URL}process_comment.php" method="POST" class="reply-form">
                <input type="hidden" name="action" value="add_reply">
                <input type="hidden" name="feed_item_id" value="${feedItemId}">
                <input type="hidden" name="parent_comment_id" value="${commentId}">
                <div class="comment-form-with-avatar">
                    <img src="${CURRENT_USER_PROFILE_PICTURE}" alt="Sua foto" class="comment-profile-picture">
                    <div class="comment-input-container">
                        <textarea name="comment_content" placeholder="Escreva sua resposta..." required></textarea>
                        <div class="form-buttons">
                            <button type="button" class="btn-cancel-reply cancel-reply-btn" data-comment-id="${commentId}">Cancelar</button>
                            <button type="submit" class="btn-send-comment" title="Responder">
                                <i class="icon-local icon-send"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        `;
        container.style.display = 'block';
        container.querySelector('textarea').focus();
        container.querySelector('form').addEventListener('submit', handleFormSubmit);
        container.querySelector('.cancel-reply-btn').addEventListener('click', () => {
            container.style.display = 'none';
            container.innerHTML = '';
        });
    }


    function handleEditClick(event) {
        const commentId = event.target.dataset.commentId;
        const commentContentElement = document.querySelector(`li.comment-item[data-comment-id="${commentId}"] .comment-text-content p`); // Seletor ajustado
        const originalContent = commentContentElement ? commentContentElement.dataset.originalContent : '';
        const replyFormContainer = document.getElementById(`replyFormContainer-${commentId}`); // Usar o mesmo contêiner

        if (!replyFormContainer) {
            console.error(`Elemento 'replyFormContainer-${commentId}' não encontrado para o comentário ${commentId}.`);
            return;
        }

        // Fechar outros formulários abertos
        document.querySelectorAll('.reply-form-container').forEach(container => {
            if (container.id !== `replyFormContainer-${commentId}`) {
                container.style.display = 'none';
                container.innerHTML = '';
            }
        });

        if (replyFormContainer.style.display === 'block' && replyFormContainer.querySelector('.edit-form')) {
            replyFormContainer.style.display = 'none';
            replyFormContainer.innerHTML = '';
        } else {
            replyFormContainer.style.display = 'block';
            replyFormContainer.innerHTML = `
                <form action="${BASE_URL}process_comment.php" method="POST" class="comment-form edit-form">
                    <input type="hidden" name="action" value="edit_comment"> 
                    <input type="hidden" name="comment_id" value="${commentId}">
                    <div class="comment-input-wrapper">
                        <textarea name="comment_content" rows="1" required>${htmlspecialchars(originalContent)}</textarea>
                        <div class="comment-form-actions">
                            <button type="button" class="btn-cancel-reply cancel-edit-btn" data-comment-id="${commentId}" title="Cancelar">
                                <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg>
                            </button>
                            <button type="button" class="btn-attach-media" title="Anexar Mídia">
                                <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.31 2.69 6 6 6s6-2.69 6-6V6h-1.5z"></path></svg>
                            </button>
                            <button type="submit" class="btn-send-comment" title="Salvar">
                                <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                            </button>
                        </div>
                    </div>
                </form>
            `;
            // Re-anexa listeners para o novo formulário e botão cancelar
            initCommentAndPostEventListeners(replyFormContainer); // Usa a função de inicialização atualizada
            replyFormContainer.querySelector('textarea').focus();
        }
    }

    function handleCancelForm(event) {
        const button = event.target;
        const formContainer = button.closest('.reply-form-container'); // Pode ser cancel-reply-btn ou cancel-edit-btn
        if (formContainer) {
            formContainer.style.display = 'none';
            formContainer.innerHTML = ''; // Limpa o conteúdo
        }
    }

    function handleDeleteClick(event) {
        const button = event.target.closest('button');
        const commentId = button.dataset.commentId;
        const feedItemId = button.dataset.feedItemId; // Obter o feed item ID do post principal

        if (confirm('Tem certeza que deseja apagar este comentário e todas as suas respostas?')) {
            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('comment_id', commentId);
            formData.append('feed_item_id', feedItemId); // Enviar o feed_item_id para a verificação de permissões

            fetch(BASE_URL + 'process_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errorData => {
                        throw new Error(errorData.message || 'Erro de rede ou servidor.');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const commentItemToDelete = document.querySelector(`li.comment-item[data-comment-id="${commentId}"]`);
                    if (commentItemToDelete) {
                        const parentRepliesContainer = commentItemToDelete.closest('.comment-replies');
                        commentItemToDelete.remove();

                        // Se era uma resposta, atualiza a contagem do botão "Ver respostas" do pai
                        if (parentRepliesContainer) {
                            const parentCommentItem = parentRepliesContainer.closest('li.comment-item');
                            if (parentCommentItem) {
                                const toggleRepliesButton = parentCommentItem.querySelector('.btn-toggle-replies');
                                const remainingReplies = parentRepliesContainer.children.length;
                                if (toggleRepliesButton) {
                                    if (remainingReplies === 0) {
                                        toggleRepliesButton.remove(); // Remove o botão se não houver mais respostas
                                        parentRepliesContainer.remove(); // Remove o container de respostas vazio
                                    } else {
                                        toggleRepliesButton.textContent = `Ver ${remainingReplies} resposta${remainingReplies > 1 ? 's' : ''}`;
                                    }
                                }
                            }
                        }
                    }
                    
                    alert(data.message);
                    // Atualizar contagem de comentários global
                    if (commentCountDisplay) {
                        commentCountDisplay.textContent = data.comment_count;
                    }
                    if (commentCountSection) {
                        commentCountSection.textContent = data.comment_count;
                    }

                    // Se não houver mais comentários, mostre a mensagem "Nenhum comentário ainda"
                    if (commentsList && commentsList.children.length === 0) {
                        const noCommentsMessage = createElement('p', ['no-comments-yet'], {}, 'Nenhum comentário ainda. Seja o primeiro a comentar!');
                        commentsList.appendChild(noCommentsMessage);
                    }

                } else {
                    alert('Erro ao apagar: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro na requisição de apagar:', error);
                alert('Ocorreu um erro ao comunicar com o servidor ao apagar o comentário.');
            });
        }
    }

    function handleToggleReplies(event) {
        const commentId = event.target.dataset.commentId;
        // Seleciona a lista de respostas dentro do mesmo comment-item
        const repliesContainer = document.querySelector(`li.comment-item[data-comment-id="${commentId}"] .comment-replies`);
        
        if (repliesContainer) {
            if (repliesContainer.style.display === 'none' || repliesContainer.style.display === '') {
                repliesContainer.style.display = 'block';
                event.target.textContent = 'Esconder respostas';
            } else {
                repliesContainer.style.display = 'none';
                const currentCount = repliesContainer.children.length;
                event.target.textContent = `Ver ${currentCount} resposta${currentCount > 1 ? 's' : ''}`;
            }
        }
    }

    // Delegar o evento de submit para todos os formulários .comment-form
    // Isso é feito uma vez, no topo, para que não precise re-registrar a cada adição de form
    function handleFormSubmit(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const actionType = formData.get('action');
        
        // CORREÇÃO: Obter o feed_item_id diretamente do formulário que foi submetido
        const feedItemId = this.querySelector('input[name="feed_item_id"]')?.value;

        if (feedItemId) {
            formData.set('feed_item_id', feedItemId); // Garante que o feed_item_id está sempre presente e correto
        } else {
            console.error("Feed Item ID não encontrado no formulário de comentário submetido.");
            alert("Erro: Não foi possível determinar o item da publicação para o comentário.");
            return;
        }

        fetch(BASE_URL + 'process_comment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errorData => {
                    throw new Error(errorData.message || 'Erro de rede ou servidor.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                this.reset(); // Limpa o formulário
                const formContainer = this.closest('.reply-form-container'); // Ajustado para o novo ID/classe
                if (formContainer) {
                    formContainer.style.display = 'none';
                    formContainer.innerHTML = ''; // Limpa o conteúdo
                }

                const noCommentsMessage = commentsList ? commentsList.querySelector('.no-comments-yet') : null;
                if (noCommentsMessage) {
                    noCommentsMessage.remove();
                }

                if (actionType === 'add_comment' || actionType === 'add_reply') {
                    // O backend agora retorna o 'level' para o novo comentário
                    // A função PHP display_comments não usa 'level' diretamente para indentação, mas sim o JS
                    const newCommentElement = renderCommentElement(data.comment, data.comment.parent_comment_id ? 1 : 0, CURRENT_USER_ID, IS_POST_OWNER);
                    
                    if (data.comment.parent_comment_id) {
                        const parentCommentItem = document.querySelector(`li.comment-item[data-comment-id="${data.comment.parent_comment_id}"]`);
                        if (parentCommentItem) {
                            let repliesContainer = parentCommentItem.querySelector('.comment-replies');
                            let toggleRepliesButton = parentCommentItem.querySelector('.btn-toggle-replies');

                            if (!repliesContainer) {
                                repliesContainer = createElement('ul', ['comment-list', 'comment-replies']); // Usar ul
                                parentCommentItem.querySelector('.comment-content-area').appendChild(repliesContainer); // Adiciona dentro da área de conteúdo
                            }
                            // Garante que o container de respostas esteja visível ao adicionar uma nova resposta
                            repliesContainer.style.display = 'block'; 

                            if (!toggleRepliesButton) {
                                toggleRepliesButton = createElement('button', ['btn-toggle-replies'], {
                                    'data-comment-id': data.comment.parent_comment_id
                                }, `Ver 1 resposta`);
                                parentCommentItem.querySelector('.comment-content-area').appendChild(toggleRepliesButton); // Adiciona dentro da área de conteúdo
                                toggleRepliesButton.addEventListener('click', handleToggleReplies); // Re-add listener
                            }
                            repliesContainer.appendChild(newCommentElement);
                            const currentCount = repliesContainer.children.length;
                            toggleRepliesButton.textContent = `Ver ${currentCount} resposta${currentCount > 1 ? 's' : ''}`;
                        }
                    } else {
                        // Adiciona o novo comentário de nível superior ao topo da lista principal
                        // Encontra o comments-list específico para este post
                        const targetCommentsList = this.closest('.comment-section-full').querySelector('.comments-list');
                        if (targetCommentsList) {
                            // Se houver uma mensagem "Nenhum comentário ainda", remove-a
                            const noCommentsMessage = targetCommentsList.querySelector('.no-comments-yet');
                            if (noCommentsMessage) {
                                noCommentsMessage.remove();
                            }
                            targetCommentsList.prepend(newCommentElement);
                        }
                    }
                } else if (actionType === 'edit_comment') {
                    const editedCommentElement = document.querySelector(`li.comment-item[data-comment-id="${data.comment.id}"] .comment-text-content p`);
                    if (editedCommentElement) {
                        editedCommentElement.innerHTML = nl2br(htmlspecialchars(data.comment.content));
                        editedCommentElement.dataset.originalContent = htmlspecialchars(data.comment.content);
                    }
                }
                
                // Re-inicializa todos os listeners para novos/atualizados elementos
                initCommentAndPostEventListeners(document.querySelector(`li.comment-item[data-comment-id="${data.comment.id}"]`) || document); 

                // Atualizar contagem de comentários global (para o post específico)
                const postCommentCountDisplay = this.closest('.post-card')?.querySelector('.comment-count-display');
                const postCommentCountSection = this.closest('.comment-section-full')?.querySelector('.comment-count');

                if (postCommentCountDisplay) {
                    postCommentCountDisplay.textContent = data.comment_count;
                }
                if (postCommentCountSection) {
                    postCommentCountSection.textContent = data.comment_count;
                }

                alert(data.message); // Exibe mensagem de sucesso
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            alert('Ocorreu um erro ao comunicar com o servidor: ' + error.message);
        });
    }

    // Fechar dropdowns ao clicar fora
    document.addEventListener('click', function(event) {
        document.querySelectorAll('.comment-actions-dropdown').forEach(dropdown => {
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });
    });

    // Inicializa todos os listeners quando o DOM estiver pronto
    // Para elementos já existentes na página
    initCommentAndPostEventListeners();

    // Adiciona o listener de submit para o formulário principal de comentário
    // Isso é feito apenas uma vez, pois o formulário principal não é recriado dinamicamente.
    // Usamos delegação de eventos para capturar formulários dentro de modais dinâmicos
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.classList.contains('comment-form')) {
            handleFormSubmit.call(form, e);
        }
    });


    // --- Lógica do album_photos.js INTEGRADA ---
    // Esta lógica foi mantida aqui, pois é específica para o modal de fotos e não é duplicada em outros lugares.
    const photoModal = document.getElementById('photoModal');
    const modalPhoto = document.getElementById('modalPhoto');
    const modalPhotoCaption = document.getElementById('modalPhotoCaption');
    const closePhotoModalBtn = document.getElementById('closePhotoModal');
    const prevPhotoBtn = document.getElementById('prevPhotoBtn');
    const nextPhotoBtn = document.getElementById('nextPhotoBtn');

    const modalLikesCount = photoModal ? photoModal.querySelector('.modal-likes-count') : null;
    const modalDislikesCount = photoModal ? photoModal.querySelector('.modal-dislikes-count') : null;
    const modalLikeBtn = photoModal ? photoModal.querySelector('.btn-like.photo-like-btn') : null; // Adicionado classe para diferenciar
    const modalDislikeBtn = photoModal ? photoModal.querySelector('.btn-dislike.photo-dislike-btn') : null; // Adicionado classe para diferenciar
    const modalCommentForm = document.getElementById('modalCommentForm');
    const modalPhotoIdCommentInput = document.getElementById('modalPhotoIdComment');
    const modalParentCommentIdInput = document.getElementById('modalParentCommentId');
    const modalCommentContentInput = modalCommentForm ? modalCommentForm.querySelector('textarea[name="comment_content"]') : null;
    const modalCommentList = document.getElementById('modalCommentList');

    const editCommentModal = document.getElementById('editCommentModal');
    const editCommentForm = document.getElementById('editCommentForm');
    const editCommentIdInput = document.getElementById('editCommentId');
    const editedCommentContentInput = document.getElementById('editedCommentContent');

    // Fechar modal de edição de comentário (se existir)
    if (editCommentModal) {
        editCommentModal.querySelector('.close-button')?.addEventListener('click', () => {
            editCommentModal.style.display = 'none';
        });
        window.addEventListener('click', (event) => {
            if (event.target === editCommentModal) {
                editCommentModal.style.display = 'none';
            }
        });
    }

    let currentPhotoIndex = 0;
    let photosData = []; // Array para armazenar todas as fotos do álbum

    // Coletar dados das fotos na página para navegação, SE os elementos existirem
    if (document.querySelectorAll('.photo-item-card').length > 0) {
        document.querySelectorAll('.photo-item-card').forEach((card, index) => {
            const img = card.querySelector('.album-photo-thumb');
            if (img) {
                photosData.push({
                    id: card.dataset.photoId,
                    src: img.dataset.originalSrc,
                    caption: img.dataset.caption,
                    index: index
                });

                card.addEventListener('click', () => {
                    currentPhotoIndex = index;
                    openPhotoModal(photosData[currentPhotoIndex]);
                });
            }
        });
    }


    // Função para abrir o modal da foto (do album_photos.js)
    async function openPhotoModal(photo) {
        if (!modalPhoto || !modalPhotoCaption || !modalLikeBtn || !modalDislikeBtn || !modalLikesCount || !modalDislikesCount || !modalPhotoIdCommentInput || !modalCommentContentInput || !photoModal || !modalCommentList) {
            console.error('Um ou mais elementos do modal de foto não foram encontrados ao tentar abrir o modal.');
            return;
        }

        modalPhoto.src = photo.src;
        modalPhoto.alt = photo.caption;
        modalPhotoCaption.textContent = photo.caption;

        // Atualiza os data-attributes para likes/dislikes e comments
        modalLikeBtn.dataset.entityId = photo.id;
        modalDislikeBtn.dataset.entityId = photo.id;
        modalLikesCount.dataset.entityId = photo.id;
        modalDislikesCount.dataset.entityId = photo.id;
        modalPhotoIdCommentInput.value = photo.id; // Para o formulário de comentário

        // Limpa formulário de comentário e parentId
        modalCommentContentInput.value = '';
        if (modalParentCommentIdInput) modalParentCommentIdInput.value = '';

        await loadPhotoLikes(photo.id);
        await loadPhotoComments(photo.id);

        photoModal.style.display = 'flex'; // Usar flex para centralizar
        document.body.style.overflow = 'hidden'; // Evita scroll na página principal
    }

    // Navegação no modal (do album_photos.js)
    if (prevPhotoBtn) {
        prevPhotoBtn.addEventListener('click', () => {
            currentPhotoIndex = (currentPhotoIndex - 1 + photosData.length) % photosData.length;
            openPhotoModal(photosData[currentPhotoIndex]);
        });
    }
    if (nextPhotoBtn) {
        nextPhotoIndex = (currentPhotoIndex + 1) % photosData.length;
        openPhotoModal(photosData[nextPhotoIndex]);
    }

    // Fechar modal (do album_photos.js)
    if (closePhotoModalBtn) {
        closePhotoModalBtn.addEventListener('click', () => {
            photoModal.style.display = 'none';
            document.body.style.overflow = ''; // Restaura scroll
        });
    }

    if (photoModal) {
        photoModal.addEventListener('click', (event) => {
            if (event.target === photoModal) {
                photoModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    }

    // --- Lógica de Likes/Dislikes (do album_photos.js, para fotos) ---
    async function loadPhotoLikes(photoId) {
        try {
            const response = await fetch(`${BASE_URL}api/handle_photo_vote.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ photoId: photoId, action: 'get_status' })
            });
            const data = await response.json();
            if (data.success) {
                updatePhotoLikesDislikesUI(photoId, data.likes, data.dislikes, data.user_vote);
            } else {
                console.error('Erro ao carregar likes da foto:', data.message);
            }
        } catch (error) {
            console.error('Erro de rede ao carregar likes da foto:', error);
        }
    }

    function updatePhotoLikesDislikesUI(photoId, likes, dislikes, userVote) {
        if (modalLikesCount) modalLikesCount.textContent = likes;
        if (modalDislikesCount) modalDislikesCount.textContent = dislikes;

        if (modalLikeBtn) {
            modalLikeBtn.classList.remove('active');
            if (userVote === 'like') {
                modalLikeBtn.classList.add('active');
            }
        }
        if (modalDislikeBtn) {
            modalDislikeBtn.classList.remove('active');
            if (userVote === 'dislike') {
                modalDislikeBtn.classList.add('active');
            }
        }
    }
});
