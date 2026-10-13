function markRead(btn) {
    const notification = btn.closest('.notification');
    notification.classList.remove('unread');
    updateBadge();
  }

  function deleteNotification(btn) {
    const notification = btn.closest('.notification');
    notification.remove();
    updateBadge();
  }

  function markAllRead() {
    document.querySelectorAll('.notification').forEach(n => n.classList.remove('unread'));
    updateBadge();
  }

  function clearAll() {
    document.getElementById('notificationList').innerHTML = '';
    updateBadge();
  }

  function filterNotifications(type, evt) {
    const e = evt || window.event;
    const allBtns = document.querySelectorAll('.tabs button');
    allBtns.forEach(b => b.classList.remove('active'));
    if (e && e.target) {
      e.target.classList.add('active');
    }

    document.querySelectorAll('.notification').forEach(n => {
      if (type === 'all') {
        n.style.display = 'flex';
      } else if (type === 'unread') {
        n.style.display = n.classList.contains('unread') ? 'flex' : 'none';
      } else {
        n.style.display = n.dataset.type === type ? 'flex' : 'none';
      }
    });
  }

  function updateBadge() {
    const unreadCount = document.querySelectorAll('.notification.unread').length;
    document.getElementById('newCount').textContent = unreadCount ? `${unreadCount} new` : '';
  }


