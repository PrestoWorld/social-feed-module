import React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Layout, Menu, Breadcrumb, Card, Input, Button, Space, Avatar, Tag, InputNumber, Select, message, Form, Tabs
} from 'antd';
import { FileText, Bulb, Picture, VideoCamera, Music, Book, Hammer, UserGroup } from '@ant-design/icons';

const { Header, Content, Sider } = Layout;

const API = '/api/headless/social-feed';

const SocialFeedScreen: React.FC = () => {
  const [activeTab, setActiveTab] = React.useState<'feed' | 'video' | 'podcast' | 'book' | 'auction' | 'gallery'>('feed');
  const queryClient = useQueryClient();

  const { data: posts = [], isLoading } = useQuery({
    queryKey: ['social-posts', activeTab],
    queryFn: async () => {
      const res = await fetch(`${API}/social_posts?type=${activeTab === 'feed' ? '' : activeTab}`);
      if (!res.ok) throw new Error('Failed to load posts');
      return res.json();
    },
  });

  const createPost = useMutation({
    mutationFn: async (data: any) => {
      const res = await fetch(`${API}/social_posts`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      if (!res.ok) throw new Error('Failed to create post');
      return res.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['social-posts', activeTab] });
      message.success('Đã đăng bài viết');
    },
  });

  const toggleReaction = useMutation({
    mutationFn: async ({ postId, type }: { postId: string; type: string }) => {
      const res = await fetch(`${API}/social_reactions`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_external_id: postId, type, user_id: 'user_1' }),
      });
      return res.json();
    },
  });

  const typeLabel = (t: string) => {
    switch (t) {
      case 'status': return 'Status';
      case 'ads': return 'Quảng cáo';
      case 'video': return 'Video';
      case 'podcast': return 'Podcast';
      case 'book_status': return 'Sách status';
      case 'book_quote': return 'Câu nói sách';
      case 'book_review': return 'Đánh giá sách';
      case 'book_event': return 'Sự kiện sách';
      case 'auction': return 'Đấu giá';
      default: return t;
    }
  };

  const typeIcon = (t: string) => {
    switch (t) {
      case 'video': return <VideoCamera />;
      case 'podcast': return <Music />;
      case 'book_status':
      case 'book_quote':
      case 'book_review':
      case 'book_event': return <Book />;
      case 'auction': return <Hammer />;
      case 'ads': return <Bulb />;
      default: return <FileText />;
    }
  };

  const handleCreatePost = (values: any) => {
    createPost.mutate(values);
  };

  const reactionIcon = (type: string) => {
    switch (type) {
      case 'like': return '👍';
      case 'love': return '❤️';
      case 'haha': return '😂';
      case 'wow': return '😮';
      case 'sad': return '😢';
      case 'angry': return '😡';
      default: return '👍';
    }
  };

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider width={220} className="site-layout-background">
        <div style={{ padding: 24, textAlign: 'center' }}>
          <h2>Social Feed</h2>
        </div>
        <Menu mode="inline" defaultSelectedKeys={['1']} style={{ height: '100%', borderRight: 0 }}>
          <Menu.Item key="feed" icon={<FileText />} onClick={() => setActiveTab('feed')}>
            Tất cả bài viết
          </Menu.Item>
          <Menu.Item key="video" icon={<VideoCamera />} onClick={() => setActiveTab('video')}>
            Video embed
          </Menu.Item>
          <Menu.Item key="podcast" icon={<Music />} onClick={() => setActiveTab('podcast')}>
            Podcast
          </Menu.Item>
          <Menu.Item key="book" icon={<Book />} onClick={() => setActiveTab('book')}>
            Book Social
          </Menu.Item>
          <Menu.Item key="auction" icon={<Hammer />} onClick={() => setActiveTab('auction')}>
            Đấu giá sách
          </Menu.Item>
          <Menu.Item key="gallery" icon={<Picture />} onClick={() => setActiveTab('gallery')}>
            Ảnh gallery
          </Menu.Item>
          <Menu.Item key="user-follows" icon={<UserGroup />} onClick={() => setActiveTab('feed')}>
            User Follows
          </Menu.Item>
        </Menu>
      </Sider>
      <Content style={{ padding: '0 24px 24px', minHeight: 280 }}>
        <Breadcrumb style={{ margin: '16px 0' }}>
          <Breadcrumb.Item>Trang chủ</Breadcrumb.Item>
          <Breadcrumb.Item>Social Feed</Breadcrumb.Item>
        </Breadcrumb>

        {activeTab === 'gallery' && (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 16 }}>
            {(posts.filter((p: any) => p.images && p.images.length > 0) as any[]).flatMap((post: any) =>
              (post.images || []).map((img: string, i: number) => (
                <Card key={`${post.external_id}-${i}`} style={{ height: 200 }}>
                  <img src={img} style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: 8 }} alt="gallery" />
                  <Card.Meta style={{ position: 'absolute', bottom: 8, left: 8, right: 8 }}>
                    <Tag color="blue">{post.user_name}</Tag>
                  </Card.Meta>
                </Card>
              ))
            )}
          </div>
        )}

        {activeTab !== 'gallery' && (
          <Space direction="vertical" style={{ gap: 24 }}>
            <Card
              title={`Tạo ${typeLabel(activeTab)} mới`}
              extra={
                <Button type="primary" onClick={() => handleCreatePost({ type: activeTab === 'feed' ? 'status' : activeTab, content: '', images: [], gallery: [] })}>
                  Đăng ngay
                </Button>
              }
            >
              <FormPost activeTab={activeTab} onSubmit={handleCreatePost} />
            </Card>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
              <h3>{typeLabel(activeTab)} - Dòng thời gian</h3>
              <Tabs defaultActiveKey="all">
                <Tabs.TabPane key="all" tab="Tất cả" />
                <Tabs.TabPane key="published" tab="Đã xuất bản" />
                <Tabs.TabPane key="draft" tab="Nháp" />
              </Tabs>
            </div>

            {isLoading ? (
              <div style={{ textAlign: 'center', padding: 40 }}>Đang tải...</div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(400px, 1fr))', gap: 16 }}>
                {posts.map((post: any) => (
                  <Card key={post.external_id} style={{ minHeight: 220 }}>
                    <Card.Meta
                      avatar={<Avatar src={post.user_avatar} size={40} />}
                      title={post.user_name}
                      description={post.content}
                    />
                    {post.media_url && (
                      <div style={{ marginTop: 12, position: 'relative' }}>
                        {post.type === 'video' || post.type === 'podcast' ? (
                          <div style={{ width: '100%', height: 200, background: '#f0f0f0', borderRadius: 8, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <span style={{ fontSize: 48 }}>{post.type === 'video' ? '🎥' : '🎵'}</span>
                          </div>
                        ) : (
                          <img src={post.media_url} style={{ width: '100%', height: 200, objectFit: 'cover', borderRadius: 8 }} alt="media" />
                        )}
                      </div>
                    )}
                    {post.images && post.images.length > 0 && (
                      <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                        {post.images.slice(0, 3).map((img: string, i: number) => (
                          <img key={i} src={img} style={{ width: 80, height: 80, objectFit: 'cover', borderRadius: 8 }} alt="post" />
                        ))}
                      </div>
                    )}
                    {post.payload && post.payload.book_title && (
                      <div style={{ marginTop: 8, fontSize: 13, color: '#666', background: '#fafafa', padding: 8, borderRadius: 4 }}>
                        {post.payload.book_title} - {post.payload.book_author || ''}
                      </div>
                    )}
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 12 }}>
                      <Tag color={post.type === 'status' ? 'blue' : post.type === 'auction' ? 'orange' : 'green'}>
                        {typeIcon(post.type)} {typeLabel(post.type)}
                      </Tag>
                      <Space>
                        <Button size="small" onClick={() => toggleReaction.mutate({ postId: post.external_id, type: 'like' })}>
                          {reactionIcon('like')} Thích
                        </Button>
                        <Button size="small">{post.comment_count} Bình luận</Button>
                        <Button size="small">{post.save_count} Lưu</Button>
                      </Space>
                    </div>
                  </Card>
                ))}
              </div>
            )}
          </Space>
        )}
      </Content>
    </Layout>
  );
};

const FormPost: React.FC<{ activeTab: string; onSubmit: (values: any) => void }> = ({ activeTab, onSubmit }) => {
  const [type, setType] = React.useState(activeTab === 'feed' ? 'status' : activeTab);
  const [content, setContent] = React.useState('');
  const [images, setImages] = React.useState<string[]>([]);
  const [mediaUrl, setMediaUrl] = React.useState('');
  const [embedUrl, setEmbedUrl] = React.useState('');

  const isBookType = ['book_status', 'book_quote', 'book_review', 'book_event'].includes(activeTab);
  const isAuctionType = activeTab === 'auction';
  const isMediaType = ['video', 'podcast'].includes(activeTab);

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        onSubmit({ type, content, images, media_url: mediaUrl, embed_url: embedUrl });
      }}
      style={{ display: 'flex', flexDirection: 'column', gap: 12 }}
    >
      <Select value={type} onChange={(v) => setType(v as any)} style={{ width: 150 }}>
        <Select.Option value="status">Status</Select.Option>
        <Select.Option value="video">Video embed</Select.Option>
        <Select.Option value="podcast">Podcast</Select.Option>
        <Select.Option value="book_status">Sách status</Select.Option>
        <Select.Option value="book_quote">Câu nói sách</Select.Option>
        <Select.Option value="book_review">Đánh giá sách</Select.Option>
        <Select.Option value="book_event">Sự kiện sách</Select.Option>
        <Select.Option value="auction">Đấu giá sách</Select.Option>
      </Select>
      <Input.TextArea
        value={content}
        onChange={(e) => setContent(e.target.value)}
        placeholder="Bạn đang nghĩ gì...?"
        rows={4}
        showCount
      />
      {isMediaType && (
        <>
          <Input
            placeholder="URL video/audio (YouTube, Spotify, ...)"
            value={mediaUrl}
            onChange={(e) => setMediaUrl(e.target.value)}
          />
          <Input placeholder="Embed URL (iframe embed)" value={embedUrl} onChange={(e) => setEmbedUrl(e.target.value)} />
        </>
      )}
      {isBookType && (
        <>
          <Input placeholder="Tiêu đề sách" />
          <Input placeholder="Tác giả" />
          <InputNumber placeholder="Số trang" min={0} />
        </>
      )}
      {isAuctionType && (
        <>
          <InputNumber placeholder="Giá khởi điểm (₫)" min={0} />
          <Input placeholder="Thời gian kết thúc (Unix timestamp)" />
        </>
      )}
      <Button type="primary" htmlType="submit">Đăng bài viết</Button>
    </form>
  );
};

export default SocialFeedScreen;
